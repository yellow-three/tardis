<div>
    <x-tardis::page-header
        :title="__('tardis::bread.bread_management')"
        :description="__('tardis::bread.manage_your_browse_read_edit_add_d5d2')"
    >
        <x-slot:action>
            <a href="{{ route('tardis.bread.create') }}" class="btn btn-primary gap-2">
                <x-tardis::icon name="plus" class="w-4 h-4" />
                {{ __('tardis::bread.new_bread') }}
            </a>
        </x-slot:action>
    </x-tardis::page-header>

    @if (session('message'))
        <div class="alert alert-success mb-4">
            <span>{{ session('message') }}</span>
        </div>
    @endif

    @if (session('error'))
        <div class="alert alert-error mb-4">
            <span>{{ session('error') }}</span>
        </div>
    @endif

    @if ($this->hasLegacyDefinitions)
        <div class="alert alert-warning mb-6">
            <x-tardis::icon name="information-circle" class="w-5 h-5 shrink-0" />
            <div class="flex-1">
                <h3 class="font-semibold">{{ __('tardis::bread.legacy_config_definitions_detected') }}</h3>
                <p class="text-sm text-base-content/70">
                    {!! __('tardis::bread.legacy_hint', [
                        'dir' => '<code class="badge badge-ghost badge-sm">config/bread</code>',
                        'command' => '<code class="badge badge-ghost badge-sm">php artisan tardis:bread:migrate</code>',
                    ]) !!}
                </p>
            </div>
        </div>
    @endif

    @if ($this->breads->isEmpty())
        <div class="card bg-base-100 border border-base-300">
            <div class="card-body text-center py-12">
                <x-tardis::icon name="table-cells" class="w-16 h-16 mx-auto text-base-content/30" />
                <h3 class="text-lg font-semibold mt-4">{{ __('tardis::bread.no_bread_definitions_found') }}</h3>
                <p class="text-base-content/60 mt-2">
                    {{ __('tardis::bread.create_a_bread_definition_to_get_91ab') }}
                </p>
            </div>
        </div>
    @else
        <div class="card bg-base-100 border border-base-300">
            <div class="overflow-x-auto">
                <table class="table">
                    <thead>
                        <tr>
                            <th scope="col">{{ __('tardis::bread.name') }}</th>
                            <th scope="col">{{ __('tardis::bread.slug') }}</th>
                            <th scope="col">{{ __('tardis::bread.source') }}</th>
                            <th scope="col">{{ __('tardis::bread.list_layout') }}</th>
                            <th scope="col">{{ __('tardis::bread.view_layout') }}</th>
                            <th class="text-right" scope="col">{{ __('tardis::bread.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($this->breads as $slug => $bread)
                            <tr>
                                <td class="font-semibold">{{ $bread->resolvedName() ?: $slug }}</td>
                                <td><code class="badge badge-ghost badge-sm">{{ $slug }}</code></td>
                                <td><span class="badge badge-info badge-sm">{{ __('tardis::bread.json') }}</span></td>
                                <td>
                                    @php
                                        $layout = $bread->layout ?? [];
                                        $listCount = count($layout['list'] ?? $layout['browse'] ?? []);
                                        $viewCount = count($layout['view'] ?? $layout['read'] ?? []);
                                    @endphp
                                    <span class="badge badge-ghost badge-sm">{{ $listCount }}</span>
                                </td>
                                <td>
                                    <span class="badge badge-ghost badge-sm">{{ $viewCount }}</span>
                                </td>
                                <td class="text-right">
                                    <div class="flex items-center justify-end gap-1">
                                        <div class="dropdown dropdown-end">
                                            <div tabindex="0" role="button" class="btn btn-ghost btn-sm gap-1" aria-label="{{ __('tardis::bread.backups_for', ['slug' => $slug]) }}">
                                                <x-tardis::icon name="clock" class="w-4 h-4" />
                                                {{ __('tardis::bread.backups') }}
                                            </div>
                                            <ul tabindex="0" class="dropdown-content menu bg-base-100 rounded-box z-10 w-72 p-2 shadow border border-base-300">
                                                @forelse ($this->backups($slug) as $backup)
                                                    <li>
                                                        <button
                                                            wire:click="rollback('{{ $slug }}', '{{ $backup['name'] }}')"
                                                            wire:confirm="Restore this backup? The current definition will be snapshotted first."
                                                            class="justify-between font-mono text-xs"
                                                        >
                                                            <span class="truncate">{{ $backup['date'] }}</span>
                                                            <span class="badge badge-warning badge-sm shrink-0">{{ __('tardis::bread.restore') }}</span>
                                                        </button>
                                                    </li>
                                                @empty
                                                    <li>
                                                        <span class="px-2 py-1 text-sm text-base-content/60">
                                                            {{ __('tardis::bread.no_backups_yet') }}
                                                        </span>
                                                    </li>
                                                @endforelse
                                            </ul>
                                        </div>
                                        <a href="{{ route('tardis.bread.edit', ['slug' => $slug]) }}" class="btn btn-ghost btn-sm">
                                            {{ __('tardis::bread.edit') }}
                                        </a>
                                        <a href="{{ route('tardis.bread.index', ['slug' => $slug]) }}" class="btn btn-ghost btn-sm">
                                            {{ __('tardis::bread.browse') }}
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>