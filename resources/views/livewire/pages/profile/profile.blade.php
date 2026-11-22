<div class="max-w-2xl">
    <x-tardis::page-header :title="__('tardis::profile.title')" :description="__('tardis::profile.description')" />

    @if (session('message'))
        <div class="alert alert-success mb-4" role="status"><span>{{ session('message') }}</span></div>
    @endif

    @php
        $accounts = app(\Tardis\Auth\UserAccounts::class);
    @endphp

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

    @if ($this->twoFactor()->available())
        @php
            $tfUser = \Tardis\Support\PanelUser::current();
            $tfEnabled = $this->twoFactor()->enabledFor($tfUser);
            $tfPending = $tfEnabled ? null : $this->twoFactor()->pendingSecret($tfUser);
            $tfRemaining = $tfEnabled ? $this->twoFactor()->remainingRecoveryCodes($tfUser) : 0;
        @endphp
        <x-tardis::card :title="__('tardis::profile.two_factor')" class="mt-6">
            <p class="mb-3 text-sm text-base-content/70">{{ __('tardis::profile.two_factor_help') }}</p>

            @if ($recoveryCodes !== [])
                {{-- A plain box, not .alert: the alert's grid pushed the code list out of it. --}}
                <div class="mb-3 rounded-box border border-warning bg-warning/10 p-4 text-sm" role="status">
                    <p>{{ __('tardis::profile.two_factor_recovery_codes') }}</p>
                    <ul class="mt-3 grid grid-cols-2 gap-x-6 gap-y-1 font-mono">
                        @foreach ($recoveryCodes as $recoveryCode)
                            <li>{{ $recoveryCode }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if ($tfEnabled)
                <p class="mb-3 text-sm"><span class="badge badge-success badge-sm">{{ __('tardis::profile.two_factor_on') }}</span>
                    {{ __('tardis::profile.two_factor_codes_left', ['count' => $tfRemaining]) }}</p>
                <div class="flex flex-wrap items-end gap-2">
                    <label class="flex flex-col gap-1">
                        <span class="text-sm font-medium">{{ __('tardis::profile.two_factor_current_code') }}</span>
                        <input type="text" wire:model="twoFactorCode" autocomplete="one-time-code" class="input input-sm w-44 font-mono" />
                    </label>
                    <button type="button" wire:click="regenerateRecoveryCodes" class="btn btn-ghost btn-sm">{{ __('tardis::profile.two_factor_new_codes') }}</button>
                    <button type="button" wire:click="disableTwoFactor" class="btn btn-error btn-outline btn-sm">{{ __('tardis::profile.two_factor_disable') }}</button>
                </div>
            @elseif ($tfPending)
                @php
                    $tfQr = $this->qrCode($tfPending);
                @endphp
                <ol class="mb-3 list-decimal space-y-2 pl-5 text-sm">
                    <li>{{ __('tardis::profile.two_factor_scan') }}</li>
                </ol>
                @if ($tfQr)
                    <div class="mb-3 w-fit rounded bg-white p-2 [&>svg]:block [&>svg]:size-48">{!! $tfQr !!}</div>
                @endif
                <p class="mb-3 text-sm">{{ __('tardis::profile.two_factor_key') }} <code class="font-mono select-all">{{ trim(chunk_split($tfPending, 4, ' ')) }}</code></p>
                <div class="flex flex-wrap items-end gap-2">
                    <label class="flex flex-col gap-1">
                        <span class="text-sm font-medium">{{ __('tardis::profile.two_factor_confirm_code') }}</span>
                        <input type="text" wire:model="twoFactorCode" autocomplete="one-time-code" class="input input-sm w-44 font-mono" />
                    </label>
                    <button type="button" wire:click="confirmTwoFactor" class="btn btn-primary btn-sm">{{ __('tardis::profile.two_factor_confirm') }}</button>
                </div>
            @else
                <button type="button" wire:click="startTwoFactor" class="btn btn-primary btn-sm w-fit">{{ __('tardis::profile.two_factor_enable') }}</button>
            @endif

            @error('twoFactorCode') <p class="mt-2 text-sm text-error" role="alert">{{ $message }}</p> @enderror
        </x-tardis::card>
    @endif

    <x-tardis::card :title="__('tardis::profile.sessions')" class="mt-6">
        <p class="mb-3 text-sm text-base-content/70">{{ __('tardis::profile.sessions_help') }}</p>
        @if ($this->sessions()->available())
            @php
                $openSessions = $this->sessions()->forUser(\Tardis\Support\PanelUser::current());
            @endphp
            <ul class="mb-4 divide-y divide-base-200">
                @foreach ($openSessions as $openSession)
                    <li wire:key="session-{{ md5($openSession['id']) }}" class="flex flex-wrap items-center gap-2 py-2 text-sm">
                        <span class="font-medium">{{ $openSession['agent'] }}</span>
                        <span class="text-base-content/60">{{ $openSession['ip'] }}</span>
                        <span class="ml-auto text-base-content/60">
                            @if ($openSession['current'])
                                <span class="badge badge-success badge-sm">{{ __('tardis::profile.this_session') }}</span>
                            @else
                                {{ $openSession['last_active'] }}
                            @endif
                        </span>
                    </li>
                @endforeach
            </ul>
        @else
            <p class="mb-4 text-sm text-base-content/60">{{ __('tardis::profile.sessions_need_database') }}</p>
        @endif
        <div class="flex flex-wrap items-end gap-2">
            <label class="flex flex-col gap-1">
                <span class="text-sm font-medium">{{ __('tardis::profile.current_password') }}</span>
                <input type="password" wire:model="sessionsPassword" autocomplete="current-password" class="input input-sm w-56" />
            </label>
            <button type="button" wire:click="logoutOtherSessions" class="btn btn-ghost btn-sm">{{ __('tardis::profile.sessions_logout_others') }}</button>
        </div>
        @error('sessionsPassword') <p class="mt-2 text-sm text-error" role="alert">{{ $message }}</p> @enderror
    </x-tardis::card>
</div>
