<div>
    <x-tardis::page-header :title="__('tardis::users.users')" :description="__('tardis::users.description')">
        <x-slot name="action">
            <button type="button" wire:click="openCreate" class="btn btn-primary btn-sm">
                <x-tardis::icon name="plus" class="w-4 h-4" />
                {{ __('tardis::users.new_user') }}
            </button>
        </x-slot>
    </x-tardis::page-header>

    @if (session('message'))
        <div class="alert alert-success mb-4" role="status">
            <span>{{ session('message') }}</span>
        </div>
    @endif

    @if ($error && ! $showEditModal && ! $showUserModal)
        <div class="alert alert-error mb-4" role="alert">
            <span>{{ $error }}</span>
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
                                <div class="flex justify-end gap-1">
                                    @if ($user['manageable'])
                                        <button type="button" wire:click="openDetails(@js($user['id']))" class="btn btn-ghost btn-sm">
                                            <x-tardis::icon name="pencil-square" class="w-4 h-4" />
                                            {{ __('tardis::users.edit') }}
                                        </button>
                                    @endif
                                    <button type="button" wire:click="openEdit(@js($user['id']))" class="btn btn-ghost btn-sm">
                                        <x-tardis::icon name="user-group" class="w-4 h-4" />
                                        {{ __('tardis::users.roles') }}
                                    </button>
                                    @if ($user['manageable'] && ! $user['self'])
                                        <button type="button" wire:click="deleteUser(@js($user['id']))" wire:confirm="{{ __('tardis::users.confirm_delete') }}" class="btn btn-ghost btn-sm text-error">
                                            {{ __('tardis::users.delete') }}
                                        </button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="py-8 text-center text-base-content/60">{{ __('tardis::users.no_users_found') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>

            <div class="mt-4">
                {{ $this->users->links() }}
            </div>
        </div>
    </div>

    @if ($showUserModal)
        <div class="modal modal-open" role="dialog" aria-modal="true" aria-labelledby="user-form-title">
            <form wire:submit="saveUser" class="modal-box">
                <h3 id="user-form-title" class="text-lg font-bold">{{ $formUserId === null ? __('tardis::users.new_user') : __('tardis::users.edit_user') }}</h3>

                @if ($error)
                    <div class="alert alert-error mt-4" role="alert"><span>{{ $error }}</span></div>
                @endif

                <div class="mt-4 space-y-3">
                    @foreach (['name' => 'text', 'email' => 'email'] as $fieldName => $inputType)
                        @if (app(\Tardis\Auth\UserAccounts::class)->column($fieldName) !== null)
                            <label class="form-control w-full">
                                <span class="label-text">{{ __('tardis::users.'.$fieldName) }}</span>
                                <input type="{{ $inputType }}" wire:model="userForm.{{ $fieldName }}" class="input w-full" autocomplete="off" />
                                @error('userForm.'.$fieldName) <span class="text-error text-xs">{{ $message }}</span> @enderror
                            </label>
                        @endif
                    @endforeach

                    @if (app(\Tardis\Auth\UserAccounts::class)->column('password') !== null)
                        <label class="form-control w-full">
                            <span class="label-text">{{ __('tardis::users.password') }}</span>
                            <input type="password" wire:model="userForm.password" class="input w-full" autocomplete="new-password" />
                            @if ($formUserId !== null)
                                <span class="text-xs text-base-content/60">{{ __('tardis::users.password_keep') }}</span>
                            @endif
                            @error('userForm.password') <span class="text-error text-xs">{{ $message }}</span> @enderror
                        </label>
                        <label class="form-control w-full">
                            <span class="label-text">{{ __('tardis::users.password_confirmation') }}</span>
                            <input type="password" wire:model="userForm.password_confirmation" class="input w-full" autocomplete="new-password" />
                        </label>
                    @endif

                    @if ($formUserId === null && $this->roles !== [])
                        <fieldset class="space-y-2">
                            <legend class="label-text mb-1">{{ __('tardis::users.roles') }}</legend>
                            @foreach ($this->roles as $role)
                                <label wire:key="create-role-{{ $role->id }}" class="flex cursor-pointer items-center gap-3">
                                    <input type="checkbox" class="checkbox checkbox-sm" wire:model="createRoleIds" value="{{ $role->id }}" />
                                    <span>{{ $role->name }}</span>
                                </label>
                            @endforeach
                        </fieldset>
                    @endif
                </div>

                <div class="modal-action">
                    <button type="button" wire:click="closeUserModal" class="btn btn-ghost">{{ __('tardis::users.cancel') }}</button>
                    <button type="submit" class="btn btn-primary">{{ __('tardis::users.save') }}</button>
                </div>
            </form>
            <div class="modal-backdrop" wire:click="closeUserModal"></div>
        </div>
    @endif

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
                        <label wire:key="role-{{ $role->id }}" class="flex cursor-pointer items-center gap-3">
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
