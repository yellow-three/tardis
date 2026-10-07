<div>
    <x-tardis::page-header
        :title="__('tardis::system.log_viewer')"
        :description="__('tardis::system.read_only_notice')"
    />

    <!-- Controls -->
    <div class="card bg-base-100 mb-6 border border-base-300">
        <div class="card-body p-4">
            <div class="flex flex-wrap items-end gap-4">
                <div class="flex flex-col gap-2 flex-1 min-w-[220px]">
                    <label class="text-xs text-base-content/60" for="log-file">{{ __('tardis::system.log_file') }}</label>
                    <select id="log-file" wire:model.live="file" class="select select-sm">
                        @foreach ($available as $name)
                            <option value="{{ $name }}">{{ $name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="flex flex-col gap-2 w-28">
                    <label class="text-xs text-base-content/60" for="log-tail">{{ __('tardis::system.tail') }}</label>
                    <input
                        id="log-tail"
                        type="number"
                        min="1"
                        max="5000"
                        wire:model.live.debounce.500ms="tail"
                        class="input input-sm"
                    />
                </div>
                <button wire:click="refresh" wire:loading.attr="disabled" class="btn btn-sm">
                    {{ __('tardis::system.refresh') }}
                </button>
            </div>
        </div>
    </div>

    <!-- Output -->
    <div class="card bg-base-100 border border-base-300">
        <div class="card-body p-4">
            @if (! $this->hasFiles)
                <p class="text-sm text-base-content/60">{{ __('tardis::system.no_log_files') }}</p>
            @elseif ($unavailable)
                <div class="alert alert-warning text-sm">{{ __('tardis::system.unavailable') }}</div>
            @elseif ($this->isEmpty)
                <p class="text-sm text-base-content/60">{{ __('tardis::system.empty_log') }}</p>
            @else
                @if ($this->isTruncated)
                    <p class="text-xs text-base-content/50 mb-2">{{ __('tardis::system.truncated_notice') }}</p>
                @endif
                <pre class="text-xs overflow-x-auto whitespace-pre-wrap break-all bg-base-200 rounded-box p-4 max-h-[60vh] overflow-y-auto"><code>@foreach ($lines as $line){{ $line }}
@endforeach</code></pre>
            @endif
        </div>
    </div>
</div>
