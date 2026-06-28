<?php

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Tardis\Models\Permission;
use Tardis\Models\Role;

new #[Title('Roles')] #[Layout('tardis::layouts.admin')] class extends Component
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
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold">Roles</h1>
            <p class="text-base-content/60 mt-1">Manage user roles and their permissions</p>
        </div>
        <button wire:click="$set('showAddModal', true)" class="btn btn-primary gap-2">
            <x-tardis::icon name="plus" class="w-4 h-4" />
            Add Role
        </button>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        @forelse ($roles as $role)
            <div class="card bg-base-100 shadow-sm">
                <div class="card-body">
                    <h3 class="card-title">{{ $role['name'] }}</h3>
                    <p class="text-sm opacity-60">{{ $role['slug'] }}</p>
                    <div class="flex flex-wrap gap-1 mt-2">
                        @foreach ($role['permissions'] as $perm)
                            <span class="badge badge-ghost badge-xs">{{ $perm['slug'] }}</span>
                        @endforeach
                        @if (empty($role['permissions']))
                            <span class="text-xs opacity-40">No permissions</span>
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
            <div class="col-span-full card bg-base-100 shadow-sm">
                <div class="card-body text-center py-12">
                    <h3 class="text-lg font-semibold">No roles found</h3>
                    <p class="opacity-60">Create a role to get started</p>
                </div>
            </div>
        @endforelse
    </div>

    @if ($showAddModal)
        <dialog class="modal modal-open">
            <div class="modal-box">
                <h3 class="font-bold text-lg">Add Role</h3>
                <form wire:submit="createRole" class="space-y-4 py-4">
                    <input type="text" wire:model="newName" class="input input-bordered w-full" placeholder="Role name" />
                    <input type="text" wire:model="newSlug" class="input input-bordered w-full" placeholder="Slug (e.g., editor)" />
                </form>
                <div class="modal-action">
                    <button wire:click="$set('showAddModal', false)" class="btn btn-ghost">Cancel</button>
                    <button wire:click="createRole" class="btn btn-primary">Create</button>
                </div>
            </div>
            <form method="dialog" class="modal-backdrop"><button wire:click="$set('showAddModal', false)">close</button></form>
        </dialog>
    @endif

    @if ($showEditModal)
        <dialog class="modal modal-open">
            <div class="modal-box w-full max-w-lg">
                <h3 class="font-bold text-lg">Edit Role Permissions</h3>
                <div class="py-4 max-h-96 overflow-y-auto">
                    @foreach ($allPermissions as $perm)
                        <label class="flex items-center gap-3 py-2 border-b border-base-200">
                            <input type="checkbox" wire:model="editRolePermissions" value="{{ $perm['id'] }}" class="checkbox checkbox-sm checkbox-primary" />
                            <div>
                                <span class="font-medium">{{ $perm['name'] }}</span>
                                <span class="text-xs opacity-50 ml-2">{{ $perm['slug'] }}</span>
                            </div>
                        </label>
                    @endforeach
                </div>
                <div class="modal-action">
                    <button wire:click="$set('showEditModal', false)" class="btn btn-ghost">Cancel</button>
                    <button wire:click="saveRolePermissions" class="btn btn-primary">Save</button>
                </div>
            </div>
            <form method="dialog" class="modal-backdrop"><button wire:click="$set('showEditModal', false)">close</button></form>
        </dialog>
    @endif

    @if ($showDeleteModal)
        <dialog class="modal modal-open">
            <div class="modal-box">
                <h3 class="font-bold text-lg">Delete Role</h3>
                <p class="py-4">Are you sure you want to delete this role? Users with this role will lose their permissions.</p>
                <div class="modal-action">
                    <button wire:click="$set('showDeleteModal', false)" class="btn btn-ghost">Cancel</button>
                    <button wire:click="deleteRole" class="btn btn-error">Delete</button>
                </div>
            </div>
            <form method="dialog" class="modal-backdrop"><button wire:click="$set('showDeleteModal', false)">close</button></form>
        </dialog>
    @endif
</div>
