@php
    $paletteEntries = app(\Tardis\Support\CommandPalette::class)->entries();
    $paletteSearchUrl = route('tardis.search');
@endphp

{{-- Ctrl+K / ⌘K. The entries are the sidebar pages and "New …" actions the user may use. --}}
<div x-data="commandPalette" x-on:keydown.window="shortcut($event)" x-on:tardis-palette.window="show()" data-search-url="{{ $paletteSearchUrl }}">
    <script type="application/json" data-palette-entries>@json($paletteEntries)</script>

    <div x-show="open" x-cloak x-transition.opacity class="fixed inset-0 z-50 flex items-start justify-center bg-base-content/30 p-4 pt-[12vh]" x-on:click.self="close()" role="dialog" aria-modal="true" aria-label="{{ __('tardis::shell.palette.title') }}">
        <div class="w-full max-w-xl overflow-hidden rounded-box border border-base-300 bg-base-100 shadow-2xl" x-on:keydown="navigate($event)">
            <label class="flex items-center gap-2 border-b border-base-300 px-4 py-3">
                <x-tardis::icon name="action.search" class="w-5 h-5 opacity-50" aria-hidden="true" />
                <span class="sr-only">{{ __('tardis::shell.palette.title') }}</span>
                <input x-ref="input" x-model="query" type="text" class="grow bg-transparent outline-none" placeholder="{{ __('tardis::shell.palette.placeholder') }}" autocomplete="off" role="combobox" aria-controls="tardis-palette-results" :aria-activedescendant="'tardis-palette-' + active" />
                <kbd class="kbd kbd-sm">{{ __('tardis::shell.palette.close_key') }}</kbd>
            </label>

            <ul id="tardis-palette-results" role="listbox" class="max-h-80 overflow-y-auto p-2">
                <template x-for="(entry, index) in results" :key="entry.url">
                    <li :id="'tardis-palette-' + index" role="option" :aria-selected="index === active">
                        <a :href="entry.url" class="flex items-center justify-between gap-3 rounded-field px-3 py-2 text-sm" :class="index === active ? 'bg-primary text-primary-content' : 'hover:bg-base-200'" x-on:mouseenter="active = index" x-on:click.prevent="go(entry)">
                            <span class="truncate" x-text="entry.title"></span>
                            <span class="shrink-0 text-xs opacity-70" x-text="entry.group"></span>
                        </a>
                    </li>
                </template>
                <li x-show="query.trim() !== ''">
                    <a :href="searchUrl()" class="flex items-center gap-2 rounded-field px-3 py-2 text-sm" :class="active === results.length ? 'bg-primary text-primary-content' : 'hover:bg-base-200'" x-on:mouseenter="active = results.length" x-on:click.prevent="search()">
                        <x-tardis::icon name="action.search" class="w-4 h-4" aria-hidden="true" />
                        <span>{{ __('tardis::shell.palette.search_records') }} “<span x-text="query.trim()"></span>”</span>
                    </a>
                </li>
                <li x-show="results.length === 0 && query.trim() === ''" class="px-3 py-2 text-sm text-base-content/60">{{ __('tardis::shell.palette.empty') }}</li>
            </ul>
        </div>
    </div>
</div>
