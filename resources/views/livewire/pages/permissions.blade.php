<?php

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Tardis\Auth\Abilities;
use Tardis\Auth\BreadAuthorization;
use Tardis\Support\ModelResolver;

new #[Title('tardis::permissions.permissions')] #[Layout('tardis::layouts.admin')] class extends Component
{
    public array $permissions = [];

    public string $newName = '';

    public string $newSlug = '';

    public string $newGroup = '';

    public bool $showAddModal = false;

    public ?int $deleteId = null;

    public bool $showDeleteModal = false;

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
        $this->loadPermissions();
    }

    public function loadPermissions(): void
    {
        $this->permissions = ModelResolver::permission()::all()->toArray();
    }

    public function createPermission(): void
    {
        $this->validate([
            'newName' => 'required|string|max:255',
            'newSlug' => 'required|string|max:255|unique:tardis_permissions,slug',
        ]);

        ModelResolver::permission()::create([
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
            ModelResolver::permission()::findOrFail($this->deleteId)->delete();
            $this->deleteId = null;
            $this->showDeleteModal = false;
            $this->loadPermissions();
        }
    }
}; ?>

<div>
    <x-tardis::page-header :title="__('tardis::permissions.permissions')" :description="__('tardis::permissions.manage_system_permissions')">
        <x-slot:action>
            <button wire:click="$set('showAddModal', true)" class="btn btn-primary gap-2">
                <x-tardis::icon name="plus" class="w-4 h-4" />
                {{ __('tardis::permissions.add_permission') }}
            </button>
        </x-slot:action>
    </x-tardis::page-header>

    <div class="card bg-base-100 border border-base-300">
        <div class="overflow-x-auto">
            <table class="table">
                <thead>
                    <tr>
                        <th scope="col">{{ __('tardis::permissions.name') }}</th>
                        <th scope="col">{{ __('tardis::permissions.slug') }}</th>
                        <th scope="col">{{ __('tardis::permissions.group') }}</th>
                        <th class="text-right" scope="col">{{ __('tardis::permissions.actions') }}</th>
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
                            <td colspan="4" class="text-center py-8 text-base-content/50">{{ __('tardis::permissions.no_permissions_found') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if ($showAddModal)
        <dialog class="modal modal-open">
            <div class="modal-box">
                <h3 class="font-bold text-lg">{{ __('tardis::permissions.add_permission') }}</h3>
                <form wire:submit="createPermission" class="space-y-4 py-4">
                    <input type="text" wire:model="newName" class="input w-full" placeholder="{{ __('tardis::permissions.permission_name') }}" />
                    <input type="text" wire:model="newSlug" class="input w-full" placeholder="{{ __('tardis::permissions.slug_e_g_browse_posts') }}" />
                    <input type="text" wire:model="newGroup" class="input w-full" placeholder="{{ __('tardis::permissions.group_optional') }}" />
                </form>
                <div class="modal-action">
                    <button wire:click="$set('showAddModal', false)" class="btn btn-ghost">{{ __('tardis::permissions.cancel') }}</button>
                    <button wire:click="createPermission" class="btn btn-primary">{{ __('tardis::permissions.create') }}</button>
                </div>
            </div>
            <form method="dialog" class="modal-backdrop"><button wire:click="$set('showAddModal', false)">{{ __('tardis::permissions.close') }}</button></form>
        </dialog>
    @endif

    @if ($showDeleteModal)
        <dialog class="modal modal-open">
            <div class="modal-box">
                <h3 class="font-bold text-lg">{{ __('tardis::permissions.delete_permission') }}</h3>
                <p class="py-4">{{ __('tardis::permissions.are_you_sure_you_want_to_651a') }}</p>
                <div class="modal-action">
                    <button wire:click="$set('showDeleteModal', false)" class="btn btn-ghost">{{ __('tardis::permissions.cancel') }}</button>
                    <button wire:click="deletePermission" class="btn btn-error">{{ __('tardis::permissions.delete') }}</button>
                </div>
            </div>
            <form method="dialog" class="modal-backdrop"><button wire:click="$set('showDeleteModal', false)">{{ __('tardis::permissions.close') }}</button></form>
        </dialog>
    @endif
</div>
