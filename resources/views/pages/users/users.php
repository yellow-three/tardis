<?php

use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Tardis\Auth\Abilities;
use Tardis\Auth\BreadAuthorization;
use Tardis\Models\Role;

new #[Title('Users')] #[Layout('tardis::layouts.admin')] class extends Component
{
    public string $search = '';

    public ?int $editUserId = null;

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

    public function getUsersProperty()
    {
        $model = $this->userModel();
        $query = $model::query()->orderBy('id');

        if ($this->search !== '') {
            $term = '%'.$this->search.'%';

            $query->where(function ($q) use ($term) {
                $q->where('email', 'like', $term)->orWhere('name', 'like', $term);
            });
        }

        $users = $query->limit(100)->get();

        $roles = DB::table('tardis_role_user as ru')
            ->join('tardis_roles as r', 'r.id', '=', 'ru.role_id')
            ->whereIn('ru.user_id', $users->modelKeys())
            ->get(['ru.user_id', 'r.name'])
            ->groupBy('user_id')
            ->map(fn ($rows) => $rows->pluck('name')->all());

        return $users->map(fn ($user) => [
            'id' => $user->getKey(),
            'name' => $user->name ?? '',
            'email' => $user->email ?? '',
            'roles' => $roles->get($user->getKey(), []),
        ])->all();
    }

    public function getRolesProperty()
    {
        return Role::query()->orderBy('name')->get(['id', 'name', 'slug'])->all();
    }

    public function openEdit(int $userId): void
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
        $roleIds = Role::query()
            ->whereIn('id', array_map('intval', $this->editRoleIds))
            ->pluck('id')
            ->all();

        $superIds = Role::query()->whereIn('slug', $this->superAdminSlugs())->pluck('id')->all();

        if ($this->wouldRemoveLastSuperAdmin($user->getKey(), $roleIds, $superIds)) {
            $this->error = 'At least one super administrator must remain.';

            return;
        }

        DB::transaction(function () use ($user, $roleIds) {
            DB::table('tardis_role_user')->where('user_id', $user->getKey())->delete();

            foreach ($roleIds as $roleId) {
                DB::table('tardis_role_user')->insert(['role_id' => $roleId, 'user_id' => $user->getKey()]);
            }
        });

        $this->closeEdit();
        session()->flash('message', 'Roles updated.');
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
