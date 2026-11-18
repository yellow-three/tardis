<div class="max-w-2xl">
    <x-tardis::page-header :title="__('tardis::profile.title')" :description="__('tardis::profile.description')" />

    @if (session('message'))
        <div class="alert alert-success mb-4" role="status"><span>{{ session('message') }}</span></div>
    @endif

    @php($accounts = app(\Tardis\Auth\UserAccounts::class))

    <form wire:submit="save">
        <x-tardis::card :title="__('tardis::profile.account')" class="mb-6">
            <div class="space-y-3">
                @foreach (['name' => 'text', 'email' => 'email'] as $fieldName => $inputType)
                    @if ($accounts->column($fieldName) !== null)
                        <label class="flex w-full flex-col gap-1">
                            <span class="text-sm font-medium">{{ __('tardis::users.'.$fieldName) }}</span>
                            <input type="{{ $inputType }}" wire:model="userForm.{{ $fieldName }}" class="input w-full" autocomplete="{{ $fieldName === 'email' ? 'email' : 'name' }}" />
                            @error('userForm.'.$fieldName) <span class="text-error text-xs">{{ $message }}</span> @enderror
                        </label>
                    @endif
                @endforeach
            </div>
        </x-tardis::card>

        @if ($accounts->column('password') !== null)
            <x-tardis::card :title="__('tardis::profile.password')" class="mb-6">
                <div class="space-y-3">
                    <label class="flex w-full flex-col gap-1">
                        <span class="text-sm font-medium">{{ __('tardis::users.password') }}</span>
                        <input type="password" wire:model="userForm.password" class="input w-full" autocomplete="new-password" />
                        <span class="text-xs text-base-content/60">{{ __('tardis::users.password_keep') }}</span>
                        @error('userForm.password') <span class="text-error text-xs">{{ $message }}</span> @enderror
                    </label>
                    <label class="flex w-full flex-col gap-1">
                        <span class="text-sm font-medium">{{ __('tardis::users.password_confirmation') }}</span>
                        <input type="password" wire:model="userForm.password_confirmation" class="input w-full" autocomplete="new-password" />
                    </label>
                    <label class="flex w-full flex-col gap-1">
                        <span class="text-sm font-medium">{{ __('tardis::profile.current_password') }}</span>
                        <input type="password" wire:model="userForm.current_password" class="input w-full" autocomplete="current-password" />
                        <span class="text-xs text-base-content/60">{{ __('tardis::profile.current_password_help') }}</span>
                        @error('userForm.current_password') <span class="text-error text-xs">{{ $message }}</span> @enderror
                    </label>
                </div>
            </x-tardis::card>
        @endif

        <button type="submit" class="btn btn-primary">{{ __('tardis::users.save') }}</button>
    </form>
</div>
