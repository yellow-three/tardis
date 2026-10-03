<?php

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Tardis\Auth\Abilities;
use Tardis\Auth\BreadAuthorization;
use Tardis\Models\Permission;
use Tardis\Models\Role;

new #[Title('tardis::roles.roles')] #[Layout('tardis::layouts.admin')] class extends Component
{
    public array $roles = [];

    public string $newName = '';

    public string $newSlug = '';

    public bool $showAddModal = false;

    public ?int $editRoleId = null;

    public array $editRolePermissions = [];

    public bool $showEditModal = false;

    public ?int $deleteId = null;

    public bool $showDeleteModal = false;

    public array $allPermissions = [];

    /**
     * Runs on every request, not only on mount: Livewire keeps component state
     * between updates, so a permission revoked after the page opened must
     * still stop the next action.
     */
    public function boot(): void
    {
        app(BreadAuthorization::class)->authorizeAbility(Abilities::ROLES);
    }

    public function mount(): void
    {
        $this->loadRoles();
        $this->allPermissions = Permission::all()->toArray();
    }

    public function loadRoles(): void
    {
        $this->roles = Role::with('permissions')->get()->toArray();
    }

    public function createRole(): void
    {
        $this->validate([
            'newName' => 'required|string|max:255',
            'newSlug' => 'required|string|max:255|unique:tardis_roles,slug',
        ]);

        Role::create([
            'name' => $this->newName,
            'slug' => $this->newSlug,
        ]);

        $this->reset(['newName', 'newSlug']);
        $this->showAddModal = false;
        $this->loadRoles();
    }

    public function editRole(int $roleId): void
    {
        $role = Role::findOrFail($roleId);
        $this->editRoleId = $roleId;
        $this->editRolePermissions = $role->permissions->pluck('id')->toArray();
        $this->showEditModal = true;
    }

    public function saveRolePermissions(): void
    {
        if ($this->editRoleId) {
            $role = Role::findOrFail($this->editRoleId);
            $role->permissions()->sync($this->editRolePermissions);
            $this->showEditModal = false;
            $this->loadRoles();
        }
    }

    public function confirmDelete(int $id): void
    {
        $this->deleteId = $id;
        $this->showDeleteModal = true;
    }

    public function deleteRole(): void
    {
        if ($this->deleteId) {
            $role = Role::findOrFail($this->deleteId);
            $role->permissions()->detach();
            $role->users()->detach();
            $role->delete();
            $this->deleteId = null;
            $this->showDeleteModal = false;
            $this->loadRoles();
        }
    }
}; ?>

<div>
    <x-tardis::page-header :title="__('tardis::roles.roles')" :description="__('tardis::roles.manage_user_roles_and_their_permissions')">
        <x-slot:action>
            <button wire:click="$set('showAddModal', true)" class="btn btn-primary gap-2">
                <x-tardis::icon name="plus" class="w-4 h-4" />
                {{ __('tardis::roles.add_role') }}
            </button>
        </x-slot:action>
    </x-tardis::page-header>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        @forelse ($roles as $role)
            <div class="card bg-base-100 border border-base-300">
                <div class="card-body">
                    <h3 class="card-title">{{ $role['name'] }}</h3>
                    <p class="text-sm text-base-content/60">{{ $role['slug'] }}</p>
                    <div class="flex flex-wrap gap-1 mt-2">
                        @foreach ($role['permissions'] as $perm)
                            <span class="badge badge-ghost badge-xs">{{ $perm['slug'] }}</span>
                        @endforeach
                        @if (empty($role['permissions']))
                            <span class="text-xs text-base-content/40">{{ __('tardis::roles.no_permissions') }}</span>
                        @endif
                    </div>
                    <div class="card-actions justify-end mt-4">
                        <button wire:click="editRole({{ $role['id'] }}" class="btn btn-ghost btn-sm">
                            <x-tardis::icon name="pencil-square" class="w-4 h-4" />
                        </button>
                        <button wire:click="confirmDelete({{ $role['id'] }})" class="btn btn-ghost btn-sm text-error">
                            <x-tardis::icon name="x-mark" class="w-4 h-4" />
                        </button>
                    </div>
                </div>
            </div>
        @empty
            <div class="col-span-full card bg-base-100">
                <div class="card-body text-center py-12">
                    <h3 class="text-lg font-semibold">{{ __('tardis::roles.no_roles_found') }}</h3>
                    <p class="text-base-content/60">{{ __('tardis::roles.create_a_role_to_get_started') }}</p>
                </div>
            </div>
        @endforelse
    </div>

    @if ($showAddModal)
        <dialog class="modal modal-open">
            <div class="modal-box">
                <h3 class="font-bold text-lg">{{ __('tardis::roles.add_role') }}</h3>
                <form wire:submit="createRole" class="space-y-4 py-4">
                    <input type="text" wire:model="newName" class="input w-full" placeholder="{{ __('tardis::roles.role_name') }}" />
                    <input type="text" wire:model="newSlug" class="input w-full" placeholder="{{ __('tardis::roles.slug_e_g_editor') }}" />
                </form>
                <div class="modal-action">
                    <button wire:click="$set('showAddModal', false)" class="btn btn-ghost">{{ __('tardis::roles.cancel') }}</button>
                    <button wire:click="createRole" class="btn btn-primary">{{ __('tardis::roles.create') }}</button>
                </div>
            </div>
            <form method="dialog" class="modal-backdrop"><button wire:click="$set('showAddModal', false)">{{ __('tardis::roles.close') }}</button></form>
        </dialog>
    @endif

    @if ($showEditModal)
        <dialog class="modal modal-open">
            <div class="modal-box w-full max-w-lg">
                <h3 class="font-bold text-lg">{{ __('tardis::roles.edit_role_permissions') }}</h3>
                <div class="py-4 max-h-96 overflow-y-auto">
                    @foreach ($allPermissions as $perm)
                        <label class="flex items-center gap-3 py-2 border-b border-base-200">
                            <input type="checkbox" wire:model="editRolePermissions" value="{{ $perm['id'] }}" class="checkbox checkbox-sm checkbox-primary" />
                            <div>
                                <span class="font-medium">{{ $perm['name'] }}</span>
                                <span class="text-xs text-base-content/50 ml-2">{{ $perm['slug'] }}</span>
                            </div>
                        </label>
                    @endforeach
                </div>
                <div class="modal-action">
                    <button wire:click="$set('showEditModal', false)" class="btn btn-ghost">{{ __('tardis::roles.cancel') }}</button>
                    <button wire:click="saveRolePermissions" class="btn btn-primary">{{ __('tardis::roles.save') }}</button>
                </div>
            </div>
            <form method="dialog" class="modal-backdrop"><button wire:click="$set('showEditModal', false)">{{ __('tardis::roles.close') }}</button></form>
        </dialog>
    @endif

    @if ($showDeleteModal)
        <dialog class="modal modal-open">
            <div class="modal-box">
                <h3 class="font-bold text-lg">{{ __('tardis::roles.delete_role') }}</h3>
                <p class="py-4">{{ __('tardis::roles.are_you_sure_you_want_to_4956') }}</p>
                <div class="modal-action">
                    <button wire:click="$set('showDeleteModal', false)" class="btn btn-ghost">{{ __('tardis::roles.cancel') }}</button>
                    <button wire:click="deleteRole" class="btn btn-error">{{ __('tardis::roles.delete') }}</button>
                </div>
            </div>
            <form method="dialog" class="modal-backdrop"><button wire:click="$set('showDeleteModal', false)">{{ __('tardis::roles.close') }}</button></form>
        </dialog>
    @endif
</div>
