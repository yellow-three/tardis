<?php

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Tardis\Auth\Abilities;
use Tardis\Auth\BreadAuthorization;
use Illuminate\Support\Facades\DB;

new #[Title('tardis::activity.activity_log')] #[Layout('tardis::layouts.admin')] class extends Component
{
    public array $activities = [];

    public string $filterAction = '';

    public string $filterType = '';

    /** @var array<int, string> */
    public array $types = [];

    /** @var array<string, string> action => label */
    public array $actions = [];

    public string $filterUser = '';

    public string $search = '';

    public int $page = 1;

    public int $perPage = 25;

    public int $totalRows = 0;

    /**
     * Runs on every request, not only on mount: Livewire keeps component state
     * between updates, so a permission revoked after the page opened must
     * still stop the next action.
     */
    public function boot(): void
    {
        app(BreadAuthorization::class)->authorizeAbility(Abilities::ACTIVITY);
    }

    public function mount(): void
    {
        $table = DB::table(\Tardis\Models\ActivityLog::TABLE);
        $this->types = (clone $table)->distinct()->orderBy('model_type')->limit(200)->pluck('model_type')->all();
        $this->actions = (clone $table)->distinct()->orderBy('action')->limit(200)->pluck('action')
            ->mapWithKeys(fn (string $action) => [$action => \Illuminate\Support\Facades\Lang::has('tardis::activity.'.$action) ? __('tardis::activity.'.$action) : $action])
            ->all();

        $this->loadActivities();
    }

    public function loadActivities(): void
    {
        $query = DB::table(\Tardis\Models\ActivityLog::TABLE);

        if ($this->filterAction) {
            $query->where('action', $this->filterAction);
        }

        if ($this->filterType) {
            $query->where('model_type', $this->filterType);
        }

        if ($this->filterUser) {
            $query->where('user_id', $this->filterUser);
        }

        if ($this->search) {
            $like = \Tardis\Support\Like::operator($query);
            $query->where(function ($q) use ($like) {
                $q->where('model_type', $like, '%'.$this->search.'%')
                    ->orWhere('action', $like, '%'.$this->search.'%');
            });
        }

        $this->totalRows = $query->count();

        $this->activities = $query
            ->orderByDesc('created_at')
            ->offset(($this->page - 1) * $this->perPage)
            ->limit($this->perPage)
            ->get()
            ->toArray();
    }

    public function updatedFilterAction(): void
    {
        $this->page = 1;
        $this->loadActivities();
    }

    public function updatedFilterType(): void
    {
        $this->page = 1;
        $this->loadActivities();
    }

    public function updatedFilterUser(): void
    {
        $this->page = 1;
        $this->loadActivities();
    }

    public function updatedSearch(): void
    {
        $this->page = 1;
        $this->loadActivities();
    }

    public function previousPage(): void
    {
        if ($this->page > 1) {
            $this->page--;
            $this->loadActivities();
        }
    }

    public function nextPage(): void
    {
        if (($this->page * $this->perPage) < $this->totalRows) {
            $this->page++;
            $this->loadActivities();
        }
    }

    public function getTotalPages(): int
    {
        return (int) ceil($this->totalRows / $this->perPage);
    }

    public function getActionColor(string $action): string
    {
        return match ($action) {
            'created' => 'success',
            'updated' => 'info',
            'deleted' => 'warning',
            'restored' => 'success',
            'force_deleted' => 'error',
            default => 'ghost',
        };
    }
}; ?>

<div>
    <x-tardis::page-header :title="__('tardis::activity.activity_log')" :description="__('tardis::activity.track_all_system_activities')" />

    <!-- Filters -->
    <div class="card bg-base-100 mb-6 border border-base-300">
        <div class="card-body p-4">
            <div class="flex flex-wrap gap-4">
                <div class="flex flex-col gap-2 flex-1 min-w-[200px]">
                    <input type="text" wire:model.live.debounce.300ms="search" class="input input-sm" placeholder="{{ __('tardis::activity.search') }}" />
                </div>
                <div class="flex flex-col gap-2">
                    <select wire:model.live="filterAction" class="select select-sm">
                        <option value="">{{ __('tardis::activity.all_actions') }}</option>
                        @foreach ($actions as $action => $label)
                            <option value="{{ $action }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="flex flex-col gap-2">
                    <select wire:model.live="filterType" class="select select-sm" aria-label="{{ __('tardis::activity.kind') }}">
                        <option value="">{{ __('tardis::activity.all_kinds') }}</option>
                        @foreach ($types as $type)
                            <option value="{{ $type }}">{{ $type }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>
    </div>

    <!-- Activities -->
    <div class="card bg-base-100 border border-base-300">
        <div class="overflow-x-auto">
            <table class="table table-sm">
                <thead>
                    <tr>
                        <th scope="col">{{ __('tardis::activity.action') }}</th>
                        <th scope="col">{{ __('tardis::activity.model') }}</th>
                        <th scope="col">{{ __('tardis::activity.user') }}</th>
                        <th scope="col">{{ __('tardis::activity.time') }}</th>
                        <th scope="col">{{ __('tardis::activity.details') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($activities as $activity)
                        <tr wire:key="activity-{{ $activity->id }}">
                            <td>
                                <span class="badge badge-{{ $this->getActionColor($activity->action) }} badge-sm">
                                    {{ $activity->action }}
                                </span>
                            </td>
                            <td class="text-sm">
                                <span class="font-medium">{{ class_basename($activity->model_type) }}</span>
                                <span class="text-base-content/50">#{{ $activity->model_id }}</span>
                            </td>
                            <td class="text-sm text-base-content/60">{{ $activity->user_id ?? __('tardis::activity.system') }}</td>
                            <td class="text-sm text-base-content/60">
                                {{ $activity->created_at }}
                                @if (! empty($activity->ip_address))
                                    <span class="block text-xs" title="{{ $activity->user_agent ?? '' }}">{{ $activity->ip_address }}</span>
                                @endif
                            </td>
                            <td class="text-xs">
                                @if (! empty($activity->old_values) || ! empty($activity->new_values))
                                    <details>
                                        <summary class="cursor-pointer text-base-content/60">{{ __('tardis::activity.show_changes') }}</summary>
                                        @if (! empty($activity->old_values))
                                            <div class="mt-1 font-medium">{{ __('tardis::activity.before') }}</div>
                                            <pre class="whitespace-pre-wrap break-all max-w-md">{{ json_encode(json_decode($activity->old_values), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</pre>
                                        @endif
                                        @if (! empty($activity->new_values))
                                            <div class="mt-1 font-medium">{{ __('tardis::activity.after') }}</div>
                                            <pre class="whitespace-pre-wrap break-all max-w-md">{{ json_encode(json_decode($activity->new_values), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</pre>
                                        @endif
                                    </details>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center py-8 text-base-content/50">{{ __('tardis::activity.no_activities_found') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($totalRows > $perPage)
            <div class="card-body p-4 border-t border-base-200">
                <div class="flex items-center justify-between">
                    <span class="text-sm text-base-content/60">
                        {{ __('tardis::activity.showing', ['from' => ($page - 1) * $perPage + 1, 'to' => min($page * $perPage, $totalRows), 'total' => $totalRows]) }}
                    </span>
                    <div class="join">
                        <button wire:click="previousPage" class="join-item btn btn-sm" {{ $page <= 1 ? 'disabled' : '' }}>«</button>
                        <span class="join-item btn btn-sm btn-disabled">{{ $page }} / {{ $this->getTotalPages() }}</span>
                        <button wire:click="nextPage" class="join-item btn btn-sm" {{ $page >= $this->getTotalPages() ? 'disabled' : '' }}>»</button>
                    </div>
                </div>
            </div>
        @endif
    </div>
</div>
