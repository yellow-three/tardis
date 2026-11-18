<?php

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;
use Tardis\Auth\Abilities;
use Tardis\Auth\BreadAuthorization;
use Tardis\Auth\PrivilegeGuard;
use Tardis\Bread\ActivityLogger;
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
    public function getUsersProperty(): LengthAwarePaginator
    {
        $model = $this->userModel();
        $query = $model::query()->orderBy((new $model)->getKeyName());

        if ($this->search !== '') {
            $term = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $this->search).'%';

            $query->where(function ($q) use ($term) {
                $q->whereRaw("email like ? escape '!'", [$term])->orWhereRaw("name like ? escape '!'", [$term]);
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
            'name' => $user->name ?? '',
            'email' => $user->email ?? '',
            'roles' => $roles->get($user->getKey(), []),
        ]);
    }

    public function getRolesProperty()
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
        return (string) config('auth.providers.users.model');
    }
};
