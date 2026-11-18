<div>
    <x-tardis::page-header
        :title="__('tardis::system.command_runner')"
        :description="__('tardis::system.confirm_run')"
    />

    @unless ($this->enabled)
        <div class="alert alert-warning mb-6">
            <div>
                <p class="font-medium">{{ __('tardis::system.runner_disabled') }}</p>
                <p class="text-sm">{{ __('tardis::system.runner_disabled_hint') }}</p>
            </div>
        </div>
    @endunless

    <!-- Command picker -->
    <div class="card bg-base-100 mb-6 border border-base-300">
        <div class="card-body p-4">
            <div class="flex flex-wrap items-end gap-4">
                <div class="flex flex-col gap-2 flex-1 min-w-[220px]">
                    <label class="text-xs text-base-content/60" for="command-select">{{ __('tardis::system.command') }}</label>
                    <select id="command-select" wire:model.live="command" class="select select-sm">
                        <option value="">{{ __('tardis::system.select_command') }}</option>
                        @foreach ($available as $name)
                            <option value="{{ $name }}">{{ $name }}</option>
                        @endforeach
                    </select>
                </div>
                <button wire:click="run" wire:loading.attr="disabled" @disabled(! $canRun) class="btn btn-sm btn-primary">
                    {{ __('tardis::system.run') }}
                </button>
            </div>

            @if ($available === [])
                <p class="text-sm text-base-content/60 mt-3">{{ __('tardis::system.no_allowed_commands') }}</p>
            @endif
        </div>
    </div>

    <!-- Allowed arguments -->
    @if ($command !== '' && $this->permittedArguments !== [])
        <div class="card bg-base-100 mb-6 border border-base-300">
            <div class="card-body p-4">
                <span class="text-sm font-medium mb-3">{{ __('tardis::system.arguments') }}</span>
                <div class="flex flex-col gap-2">
                    @foreach ($this->permittedArguments as $argument)
                        <label
                            wire:key="argument-{{ $argument }}"
                            class="flex items-center gap-3 cursor-pointer"
                        >
                            <input
                                type="checkbox"
                                value="{{ $argument }}"
                                wire:model="arguments"
                                class="checkbox checkbox-sm"
                            />
                            <span class="font-mono text-sm">{{ $argument }}</span>
                        </label>
                    @endforeach
                </div>
            </div>
        </div>
    @endif

    <!-- Result -->
    @if ($denied)
        <div class="alert alert-error mb-6">{{ __('tardis::system.not_allowed') }}</div>
    @endif

    @if ($failure !== '')
        <div class="alert alert-error mb-6">{{ $failure }}</div>
    @endif

    @if ($output !== '' || $exitCode !== null)
        <div class="card bg-base-100 border border-base-300">
            <div class="card-body p-4">
                <div class="flex items-center justify-between mb-3">
                    <span class="text-sm font-medium">{{ __('tardis::system.output') }}</span>
                    @if ($exitCode !== null)
                        <span class="badge badge-sm {{ $exitCode === 0 ? 'badge-success' : 'badge-error' }}">
                            {{ __('tardis::system.exit_code') }}: {{ $exitCode }}
                        </span>
                    @endif
                </div>
                <pre class="text-xs overflow-x-auto whitespace-pre-wrap break-all bg-base-200 rounded-box p-4 max-h-[50vh] overflow-y-auto"><code>{{ $output !== '' ? $output : __('tardis::system.no_output') }}</code></pre>
            </div>
        </div>
    @endif
</div>
