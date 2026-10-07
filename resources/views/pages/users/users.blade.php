<div>
    <x-tardis::page-header :title="__('tardis::users.users')" :description="__('tardis::users.assign_roles_to_the_users_of_3707')" />

    @if (session('message'))
        <div class="alert alert-success mb-4" role="status">
            <span>{{ session('message') }}</span>
        </div>
    @endif

    <div class="card bg-base-100 mb-6 border border-base-300">
        <div class="card-body p-4">
            <label class="input flex w-full items-center gap-2">
                <x-tardis::icon name="magnifying-glass" class="w-4 h-4 opacity-50" aria-hidden="true" />
                <span class="sr-only">{{ __('tardis::users.search_users') }}</span>
                <input type="search" wire:model.live.debounce.300ms="search" class="grow" placeholder="{{ __('tardis::users.search_by_name_or_email') }}" autocomplete="off" />
            </label>
        </div>
    </div>

    <div class="card bg-base-100 border border-base-300">
        <div class="overflow-x-auto">
            <table class="table">
                <thead>
                    <tr>
                        <th scope="col">{{ __('tardis::users.user') }}</th>
                        <th scope="col">{{ __('tardis::users.roles') }}</th>
                        <th class="text-right" scope="col">{{ __('tardis::users.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($this->users as $user)
                        <tr wire:key="user-{{ $user['id'] }}">
                            <td>
                                <div class="font-semibold">{{ $user['name'] ?: $user['email'] }}</div>
                                @if ($user['name'])
                                    <div class="text-xs text-base-content/50">{{ $user['email'] }}</div>
                                @endif
                            </td>
                            <td>
                                @forelse ($user['roles'] as $roleName)
                                    <span class="badge badge-sm badge-ghost">{{ $roleName }}</span>
                                @empty
                                    <span class="text-sm text-base-content/50">{{ __('tardis::users.no_role') }}</span>
                                @endforelse
                            </td>
                            <td class="text-right">
                                <button wire:click="openEdit({{ $user['id'] }})" class="btn btn-ghost btn-sm">
                                    <x-tardis::icon name="user-group" class="w-4 h-4" />
                                    {{ __('tardis::users.roles') }}
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="py-8 text-center text-base-content/60">{{ __('tardis::users.no_users_found') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if ($showEditModal)
        <div class="modal modal-open" role="dialog" aria-modal="true" aria-labelledby="edit-roles-title">
            <div class="modal-box">
                <h3 id="edit-roles-title" class="text-lg font-bold">{{ __('tardis::users.roles') }}</h3>

                @if ($error)
                    <div class="alert alert-error mt-4" role="alert">
                        <span>{{ $error }}</span>
                    </div>
                @endif

                <div class="mt-4 space-y-2">
                    @forelse ($this->roles as $role)
                        <label class="flex cursor-pointer items-center gap-3">
                            <input type="checkbox" class="checkbox checkbox-sm" wire:model="editRoleIds" value="{{ $role->id }}" />
                            <span>{{ $role->name }}</span>
                            <span class="text-xs text-base-content/50">{{ $role->slug }}</span>
                        </label>
                    @empty
                        <p class="text-sm text-base-content/60">{{ __('tardis::users.no_roles_exist_yet_create_one_e102') }}</p>
                    @endforelse
                </div>

                <div class="modal-action">
                    <button wire:click="closeEdit" class="btn btn-ghost">{{ __('tardis::users.cancel') }}</button>
                    <button wire:click="saveRoles" class="btn btn-primary">{{ __('tardis::users.save') }}</button>
                </div>
            </div>
            <div class="modal-backdrop" wire:click="closeEdit"></div>
        </div>
    @endif
</div>
