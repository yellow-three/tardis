<div>
    <x-tardis::page-header
        :title="__('tardis::system.diagnostics')"
        :description="__('tardis::system.read_only_notice')"
    >
        <x-slot:actions>
            <button wire:click="$refresh" wire:loading.attr="disabled" class="btn btn-sm btn-primary">
                {{ __('tardis::system.run_checks') }}
            </button>
        </x-slot:actions>
    </x-tardis::page-header>

    <!-- Summary -->
    <div class="card bg-base-100 mb-6 border border-base-300">
        <div class="card-body p-4">
            <div class="flex flex-wrap items-center gap-3">
                <span class="badge badge-{{ $this->summaryColor }} badge-lg">
                    {{ $this->summaryText }}
                </span>
                <div class="stats stats-horizontal bg-transparent">
                    <div class="stat px-4 py-2">
                        <div class="stat-title text-xs">{{ __('tardis::system.passed') }}</div>
                        <div class="stat-value text-success text-2xl">{{ $this->passedCount() }}</div>
                    </div>
                    <div class="stat px-4 py-2">
                        <div class="stat-title text-xs">{{ __('tardis::system.warnings') }}</div>
                        <div class="stat-value text-warning text-2xl">{{ $this->warningCount() }}</div>
                    </div>
                    <div class="stat px-4 py-2">
                        <div class="stat-title text-xs">{{ __('tardis::system.failed') }}</div>
                        <div class="stat-value text-error text-2xl">{{ $this->failedCount() }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Checks -->
    <div class="card bg-base-100 border border-base-300">
        <div class="overflow-x-auto">
            <table class="table table-sm">
                <thead>
                    <tr>
                        <th scope="col">{{ __('tardis::system.status') }}</th>
                        <th scope="col">{{ __('tardis::system.check') }}</th>
                        <th scope="col">{{ __('tardis::system.details') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($this->checks as $check)
                        <tr wire:key="check-{{ $check->name }}">
                            <td>
                                @if ($check->isOk())
                                    <span class="badge badge-sm badge-success">{{ __('tardis::system.ok') }}</span>
                                @elseif ($check->isWarning())
                                    <span class="badge badge-sm badge-warning">{{ __('tardis::system.warn') }}</span>
                                @else
                                    <span class="badge badge-sm badge-error">{{ __('tardis::system.error') }}</span>
                                @endif
                            </td>
                            <td class="text-sm">
                                <span class="font-medium">{{ $check->name }}</span>
                                <p class="text-base-content/60">{{ $check->message }}</p>
                            </td>
                            <td class="text-sm text-base-content/60">
                                @if ($check->hint !== null)
                                    {{ $check->hint }}
                                @else
                                    <span class="text-base-content/40">{{ __('tardis::system.no_issues') }}</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="text-center py-8 text-base-content/50">
                                {{ __('tardis::system.no_issues') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
