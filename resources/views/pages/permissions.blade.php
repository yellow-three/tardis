<?php

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Tardis\Models\Permission;

new #[Title('Permissions')] #[Layout('tardis::layouts.admin')] class extends Component
{
    public array $permissions = [];

    public string $newName = '';

    public string $newSlug = '';

    public string $newGroup = '';

    public bool $showAddModal = false;

    public ?int $deleteId = null;

    public bool $showDeleteModal = false;

    public function mount(): void
    {
        $this->loadPermissions();
    }

    public function loadPermissions(): void
    {
        $this->permissions = Permission::all()->toArray();
    }

    public function createPermission(): void
    {
        $this->validate([
            'newName' => 'required|string|max:255',
            'newSlug' => 'required|string|max:255|unique:tardis_permissions,slug',
        ]);

        Permission::create([
            'name' => $this->newName,
            'slug' => $this->newSlug,
            'group' => $this->newGroup ?: null,
        ]);

        $this->reset(['newName', 'newSlug', 'newGroup']);
        $this->showAddModal = false;
        $this->loadPermissions();
    }

    public function confirmDelete(int $id): void
    {
        $this->deleteId = $id;
        $this->showDeleteModal = true;
    }

    public function deletePermission(): void
    {
        if ($this->deleteId) {
            Permission::findOrFail($this->deleteId)->delete();
            $this->deleteId = null;
            $this->showDeleteModal = false;
            $this->loadPermissions();
        }
    }
}; ?>

<div>
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold">Permissions</h1>
            <p class="text-base-content/60 mt-1">Manage system permissions</p>
        </div>
        <button wire:click="$set('showAddModal', true)" class="btn btn-primary gap-2">
            <x-tardis::icon name="plus" class="w-4 h-4" />
            Add Permission
        </button>
    </div>

    <div class="card bg-base-100 shadow-sm">
        <div class="overflow-x-auto">
            <table class="table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Slug</th>
                        <th>Group</th>
                        <th class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($permissions as $permission)
                        <tr>
                            <td class="font-semibold">{{ $permission['name'] }}</td>
                            <td><code class="badge badge-ghost badge-sm">{{ $permission['slug'] }}</code></td>
                            <td>{{ $permission['group'] ?? '-' }}</td>
                            <td class="text-right">
                                <button wire:click="confirmDelete({{ $permission['id'] }})" class="btn btn-ghost btn-xs text-error">
                                    <x-tardis::icon name="x-mark" class="w-4 h-4" />
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="text-center py-8 opacity-50">No permissions found</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if ($showAddModal)
        <dialog class="modal modal-open">
            <div class="modal-box">
                <h3 class="font-bold text-lg">Add Permission</h3>
                <form wire:submit="createPermission" class="space-y-4 py-4">
                    <input type="text" wire:model="newName" class="input input-bordered w-full" placeholder="Permission name" />
                    <input type="text" wire:model="newSlug" class="input input-bordered w-full" placeholder="Slug (e.g., browse posts)" />
                    <input type="text" wire:model="newGroup" class="input input-bordered w-full" placeholder="Group (optional)" />
                </form>
                <div class="modal-action">
                    <button wire:click="$set('showAddModal', false)" class="btn btn-ghost">Cancel</button>
                    <button wire:click="createPermission" class="btn btn-primary">Create</button>
                </div>
            </div>
            <form method="dialog" class="modal-backdrop"><button wire:click="$set('showAddModal', false)">close</button></form>
        </dialog>
    @endif

    @if ($showDeleteModal)
        <dialog class="modal modal-open">
            <div class="modal-box">
                <h3 class="font-bold text-lg">Delete Permission</h3>
                <p class="py-4">Are you sure you want to delete this permission?</p>
                <div class="modal-action">
                    <button wire:click="$set('showDeleteModal', false)" class="btn btn-ghost">Cancel</button>
                    <button wire:click="deletePermission" class="btn btn-error">Delete</button>
                </div>
            </div>
            <form method="dialog" class="modal-backdrop"><button wire:click="$set('showDeleteModal', false)">close</button></form>
        </dialog>
    @endif
</div>
