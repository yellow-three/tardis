<?php

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;
use Tardis\Auth\Abilities;
use Tardis\Auth\BreadAuthorization;
use Tardis\Auth\PrivilegeGuard;
use Tardis\Auth\UserAccounts;
use Tardis\Bread\ActivityLogger;
use Tardis\Support\Like;
use Tardis\Support\ModelResolver;

new #[Title('tardis::users.users')] #[Layout('tardis::layouts.admin')] class extends Component
{
    use WithPagination;

    public const PER_PAGE = 25;

    public string $search = '';

    public int|string|null $editUserId = null;

    /** @var array<int, int|string> */
    public array $editRoleIds = [];

    public bool $showEditModal = false;

    public ?string $error = null;

    /** The account form: create when $formUserId is null, else edit. */
    public bool $showUserModal = false;

    public int|string|null $formUserId = null;

    /** @var array{name: string, email: string, password: string, password_confirmation: string} */
    public array $userForm = ['name' => '', 'email' => '', 'password' => '', 'password_confirmation' => ''];

    /** @var array<int, int|string> roles given to a user being created */
    public array $createRoleIds = [];

    /**
     * Runs on every request so a revoked permission stops the next action.
     */
    public function boot(): void
    {
        app(BreadAuthorization::class)->authorizeAbility(Abilities::USERS);
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    /**
     * One page of users with their Tardis role names. Paged rather than cut
     * off at a fixed count, so every user stays reachable.
     */
    #[Computed]
    public function users(): LengthAwarePaginator
    {
        $model = $this->userModel();
        $query = $model::query()->orderBy((new $model)->getKeyName());

        $columns = array_values(array_filter([$this->accounts()->column('email'), $this->accounts()->column('name')]));

        if ($this->search !== '' && $columns !== []) {
            $term = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $this->search).'%';

            $query->where(function ($q) use ($term, $columns) {
                foreach ($columns as $column) {
                    $q->orWhere(fn ($inner) => $inner->whereRaw(Like::clause($q->getQuery(), $q->getGrammar()->wrap($column))." ? escape '!'", [$term]));
                }
            });
        }

        $page = $query->paginate(self::PER_PAGE);

        $roles = DB::table('tardis_role_user as ru')
            ->join('tardis_roles as r', 'r.id', '=', 'ru.role_id')
            ->whereIn('ru.user_id', $page->getCollection()->modelKeys())
            ->get(['ru.user_id', 'r.name'])
            ->groupBy('user_id')
            ->map(fn ($rows) => $rows->pluck('name')->all());

        return $page->through(fn ($user) => [
            'id' => $user->getKey(),
            ...$this->accounts()->describe($user),
            'roles' => $roles->get($user->getKey(), []),
            'manageable' => app(PrivilegeGuard::class)->mayManageUser(auth()->user(), $user),
            'self' => auth()->id() !== null && (string) auth()->id() === (string) $user->getKey(),
        ]);
    }

    #[Computed]
    public function roles()
    {
        return ModelResolver::role()::query()->orderBy('name')->get(['id', 'name', 'slug'])->all();
    }

    public function openEdit(int|string $userId): void
    {
        $user = $this->userModel()::query()->findOrFail($userId);

        $this->editUserId = $user->getKey();
        $this->editRoleIds = DB::table('tardis_role_user')
            ->where('user_id', $user->getKey())
            ->orderBy('role_id')
            ->pluck('role_id')
            ->all();
        $this->error = null;
        $this->showEditModal = true;
    }

    public function closeEdit(): void
    {
        $this->showEditModal = false;
        $this->editUserId = null;
        $this->editRoleIds = [];
        $this->error = null;
    }

    public function saveRoles(): void
    {
        if ($this->editUserId === null) {
            return;
        }

        $user = $this->userModel()::query()->findOrFail($this->editUserId);

        // Only roles that exist can be granted, whatever the client sent.
        $roleIds = ModelResolver::role()::query()
            ->whereIn('id', array_map('intval', $this->editRoleIds))
            ->pluck('id')
            ->all();

        $superIds = ModelResolver::role()::query()->whereIn('slug', $this->superAdminSlugs())->pluck('id')->all();

        $current = DB::table('tardis_role_user')->where('user_id', $user->getKey())->pluck('role_id')->all();
        $denial = app(PrivilegeGuard::class)->roleChangeDenial(auth()->user(), $current, $roleIds);

        if ($denial !== null) {
            $this->error = __($denial === 'super_admin' ? 'tardis::users.cannot_change_super_admin' : 'tardis::users.cannot_assign_beyond_own');

            return;
        }

        if ($this->wouldRemoveLastSuperAdmin($user->getKey(), $roleIds, $superIds)) {
            $this->error = __('tardis::users.super_admin_must_remain');

            return;
        }

        DB::transaction(function () use ($user, $roleIds) {
            DB::table('tardis_role_user')->where('user_id', $user->getKey())->delete();

            foreach ($roleIds as $roleId) {
                DB::table('tardis_role_user')->insert(['role_id' => $roleId, 'user_id' => $user->getKey()]);
            }
        });

        $slugs = fn (array $ids) => ModelResolver::role()::query()->whereIn('id', $ids)->orderBy('slug')->pluck('slug')->all();
        app(ActivityLogger::class)->audit('tardis.user_roles', 'roles_changed', [
            'user_id' => $user->getKey(),
            'added' => $slugs(array_diff(array_map('intval', $roleIds), array_map('intval', $current))),
            'removed' => $slugs(array_diff(array_map('intval', $current), array_map('intval', $roleIds))),
        ], $user->getKey());

        $this->closeEdit();
        session()->flash('message', __('tardis::users.roles_updated'));
    }

    public function openCreate(): void
    {
        $this->resetErrorBag();
        $this->reset('userForm', 'createRoleIds', 'formUserId', 'error');
        $this->showUserModal = true;
    }

    public function openDetails(int|string $userId): void
    {
        $user = $this->userModel()::query()->findOrFail($userId);

        if (! app(PrivilegeGuard::class)->mayManageUser(auth()->user(), $user)) {
            abort(403);
        }

        $this->resetErrorBag();
        $this->reset('userForm', 'error');
        $this->formUserId = $user->getKey();
        $this->userForm = [...$this->userForm, ...$this->accounts()->describe($user)];
        $this->showUserModal = true;
    }

    public function closeUserModal(): void
    {
        $this->showUserModal = false;
        $this->reset('userForm', 'createRoleIds', 'formUserId', 'error');
    }

    public function saveUser(): void
    {
        $this->error = null;
        $creating = $this->formUserId === null;
        $user = $creating ? null : $this->userModel()::query()->findOrFail($this->formUserId);

        if ($user !== null && ! app(PrivilegeGuard::class)->mayManageUser(auth()->user(), $user)) {
            abort(403);
        }

        $this->validate($this->accounts()->rules($user?->getKey(), passwordRequired: $creating));

        $roleIds = $creating
            ? ModelResolver::role()::query()->whereIn('id', array_map('intval', $this->createRoleIds))->pluck('id')->all()
            : [];

        if ($creating && ($denial = app(PrivilegeGuard::class)->roleChangeDenial(auth()->user(), [], $roleIds)) !== null) {
            $this->error = __($denial === 'super_admin' ? 'tardis::users.cannot_change_super_admin' : 'tardis::users.cannot_assign_beyond_own');

            return;
        }

        $data = ['name' => $this->userForm['name'], 'email' => $this->userForm['email'], 'password' => $this->userForm['password']];

        if ($creating) {
            $user = DB::transaction(function () use ($data, $roleIds) {
                $user = $this->accounts()->create($data);

                foreach ($roleIds as $roleId) {
                    DB::table('tardis_role_user')->insert(['role_id' => $roleId, 'user_id' => $user->getKey()]);
                }

                return $user;
            });
        } else {
            $this->accounts()->update($user, $data);
        }

        app(ActivityLogger::class)->audit('tardis.users', $creating ? 'user_created' : 'user_updated', [
            'user_id' => $user->getKey(),
            'password_changed' => $data['password'] !== '',
        ], $user->getKey());

        $this->closeUserModal();
        session()->flash('message', __($creating ? 'tardis::users.user_created' : 'tardis::users.user_updated'));
    }

    public function deleteUser(int|string $userId): void
    {
        $user = $this->userModel()::query()->findOrFail($userId);

        if ((string) auth()->id() === (string) $user->getKey()) {
            $this->error = __('tardis::users.cannot_delete_self');

            return;
        }

        if (! app(PrivilegeGuard::class)->mayManageUser(auth()->user(), $user)) {
            abort(403);
        }

        $superIds = ModelResolver::role()::query()->whereIn('slug', $this->superAdminSlugs())->pluck('id')->all();

        if ($this->wouldRemoveLastSuperAdmin($user->getKey(), [], $superIds)) {
            $this->error = __('tardis::users.super_admin_must_remain');

            return;
        }

        $this->accounts()->delete($user);
        app(ActivityLogger::class)->audit('tardis.users', 'user_deleted', ['user_id' => $userId], $userId);
        session()->flash('message', __('tardis::users.user_deleted'));
    }

    protected function accounts(): UserAccounts
    {
        return app(UserAccounts::class);
    }

    /**
     * @param  array<int, int>  $newRoleIds
     * @param  array<int, int>  $superIds
     */
    protected function wouldRemoveLastSuperAdmin(mixed $userId, array $newRoleIds, array $superIds): bool
    {
        if ($superIds === []) {
            return false;
        }

        $holdsNow = DB::table('tardis_role_user')
            ->where('user_id', $userId)
            ->whereIn('role_id', $superIds)
            ->exists();

        $keeps = array_intersect($newRoleIds, $superIds) !== [];

        if (! $holdsNow || $keeps) {
            return false;
        }

        $others = DB::table('tardis_role_user')
            ->where('user_id', '!=', $userId)
            ->whereIn('role_id', $superIds)
            ->exists();

        return ! $others;
    }

    /**
     * @return array<int, string>
     */
    protected function superAdminSlugs(): array
    {
        return array_values(array_filter(array_map('strval', (array) config('tardis.authorization.super_admin_roles', []))));
    }

    protected function userModel(): string
    {
        return $this->accounts()->model();
    }
};
