{{--
    Lets the signed-in user choose how the panel looks: a mode (light, dark or
    follow the system) and which theme fills the light and the dark slot. The
    choice is applied at once and saved through the theme store.
--}}
<div class="card bg-base-100 mb-6 border border-base-300" x-data x-show="$store.theme.themes.length > 0">
    <div class="card-body p-4">
        <h3 class="card-title text-sm font-semibold flex items-center gap-2">
            <x-tardis::icon name="paint-brush" class="w-4 h-4" />
            {{ __('tardis::appearance.title') }}
        </h3>

        <div class="join mt-2" role="radiogroup" aria-label="{{ __('tardis::appearance.mode') }}">
            @foreach (['light', 'dark', 'system'] as $themeMode)
                <button
                    type="button"
                    role="radio"
                    class="join-item btn btn-sm"
                    :class="$store.theme.mode === '{{ $themeMode }}' ? 'btn-primary' : ''"
                    :aria-checked="$store.theme.mode === '{{ $themeMode }}'"
                    @click="$store.theme.setMode('{{ $themeMode }}')"
                >{{ __('tardis::appearance.modes.'.$themeMode) }}</button>
            @endforeach
        </div>

        @foreach (['light', 'dark'] as $slot)
            <h4 class="mt-4 text-xs font-semibold uppercase tracking-wide text-base-content/50">{{ __('tardis::appearance.slot.'.$slot) }}</h4>
            <div class="grid grid-cols-1 gap-3 md:grid-cols-2 lg:grid-cols-3">
                <template x-for="theme in $store.theme.{{ $slot }}Themes" :key="theme.name">
                    <button
                        type="button"
                        class="flex items-center gap-3 rounded-lg border p-3 text-left transition-colors"
                        :class="$store.theme.{{ $slot }} === theme.name ? 'border-primary bg-base-200' : 'border-base-300 hover:bg-base-200'"
                        :aria-pressed="$store.theme.{{ $slot }} === theme.name"
                        @click="$store.theme.set{{ ucfirst($slot) }}(theme.name)"
                    >
                        <span class="flex gap-1">
                            <template x-for="(color, i) in theme.preview" :key="i">
                                <span class="h-6 w-6 shrink-0 rounded-full border border-base-300" :style="`background-color: ${color}`"></span>
                            </template>
                        </span>
                        <span class="min-w-0 flex-1">
                            <span class="block truncate text-sm font-medium" x-text="theme.label"></span>
                            <span class="badge badge-ghost badge-xs" x-text="theme.builtin ? @js(__('tardis::appearance.builtin')) : @js(__('tardis::appearance.custom'))"></span>
                        </span>
                    </button>
                </template>
            </div>
        @endforeach
    </div>
</div>
