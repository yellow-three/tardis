<div x-data="{
    notification: null,
    notificationTimeout: null,
    showNotification(type, message) {
        this.notification = { type, message };
        clearTimeout(this.notificationTimeout);
        this.notificationTimeout = setTimeout(() => { this.notification = null }, 3000);
    }
}" x-init="
    // Ctrl+S shortcut
    window.addEventListener('keydown', (e) => {
        if ((e.ctrlKey || e.metaKey) && e.key === 's') {
            e.preventDefault();
            $wire.save();
            showNotification('success', @js(__('tardis::settings.saved')));
        }
    });
    // URL hash support
    const hash = window.location.hash.replace('#', '');
    if (hash) { $wire.setActiveGroup(hash); }
" @set-group.window="$wire.setActiveGroup($event.detail); window.location.hash = $event.detail">
    <!-- Notification Toast -->
    <template x-if="notification">
        <div class="fixed top-4 right-4 z-50" x-transition>
            <div class="alert" :class="{
                'alert-success': notification.type === 'success',
                'alert-error': notification.type === 'error',
                'alert-info': notification.type === 'info'
            }">
                <span x-text="notification.message"></span>
            </div>
        </div>
    </template>

    <!-- Header -->
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold">{{ __('tardis::settings.settings') }}</h1>
            <p class="text-base-content/60 mt-1">{{ __('tardis::settings.manage_your_application_configuration') }}</p>
        </div>

        <div class="flex gap-2">
            <div class="relative">
                <x-tardis::icon name="magnifying-glass" class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-base-content/50" />
                <input type="text" wire:model.live.debounce.300ms="search" class="input input-sm pl-10 w-64" placeholder="{{ __('tardis::settings.search_settings') }}" />
            </div>
            <button wire:click="$set('showAddGroupModal', true)" class="btn btn-outline btn-sm gap-2">
                <x-tardis::icon name="plus" class="w-4 h-4" />
                {{ __('tardis::settings.add_group') }}
            </button>
            <button wire:click="openImportModal" class="btn btn-outline btn-sm gap-2">
                <x-tardis::icon name="folder" class="w-4 h-4" />
                {{ __('tardis::settings.import') }}
            </button>
            <button wire:click="openExportModal" class="btn btn-outline btn-sm gap-2">
                <x-tardis::icon name="check" class="w-4 h-4" />
                {{ __('tardis::settings.export') }}
            </button>
            <button wire:click="$set('showAddModal', true)" class="btn btn-primary btn-sm gap-2">
                <x-tardis::icon name="plus" class="w-4 h-4" />
                {{ __('tardis::settings.add_setting') }}
            </button>
        </div>
    </div>

    <x-tardis::theme-picker />

    <!-- Horizontal Group Tabs (Voyager II style) -->
    @php $filteredGroups = $this->getFilteredGroups(); @endphp
    @if (count($filteredGroups) > 0)
        <div class="tabs tabs-box mb-6 bg-base-100 overflow-x-auto">
            @foreach ($filteredGroups as $groupKey => $group)
                <button wire:click="setActiveGroup('{{ $groupKey }}')"
                        role="tab"
                        class="tab tab-lg gap-2 {{ $activeGroup === $groupKey ? 'tab-active font-semibold' : '' }}">
                    @if ($group['icon'])
                        <x-tardis::icon :name="$group['icon']" class="w-5 h-5" />
                    @endif
                    <span>{{ $group['label'] }}</span>
                    <span class="badge badge-ghost badge-sm">{{ count($group['settings']) }}</span>
                </button>
            @endforeach
        </div>

        <!-- Active Group Settings Card -->
        @if ($activeGroup && isset($filteredGroups[$activeGroup]))
            <div class="card bg-base-100 border border-base-300">
                <div class="card-body">
                    <h2 class="card-title text-xl flex items-center gap-2">
                        @if ($filteredGroups[$activeGroup]['icon'])
                            <x-tardis::icon :name="$filteredGroups[$activeGroup]['icon']" class="w-5 h-5" />
                        @endif
                        {{ $filteredGroups[$activeGroup]['label'] }}
                        <span class="text-sm text-base-content/40 font-normal">{{ __('tardis::settings.settings_2') }}</span>
                    </h2>

                    <div class="divider mt-2 mb-0"></div>

                    <div class="space-y-1">
                        @foreach ($filteredGroups[$activeGroup]['settings'] as $setting)
                            <div class="setting-row py-4 {{ !$loop->last ? 'border-b border-base-200' : '' }}">
                                <!-- Label & Info -->
                                <div class="mb-2">
                                    <div class="flex items-center gap-2">
                                        <label for="setting-{{ $setting['uuid'] }}" class="font-medium text-sm">
                                            {{ $setting['name'] }}
                                        </label>
                                        @if ($setting['translatable'])
                                            <span class="badge badge-ghost badge-xs gap-1">
                                                <x-tardis::icon name="text" class="w-3 h-3" />
                                                {{ __('tardis::settings.translatable') }}
                                            </span>
                                        @endif
                                        @if (!empty($setting['validation']))
                                            <span class="badge badge-ghost badge-xs gap-1">
                                                <x-tardis::icon name="check-circle" class="w-3 h-3" />
                                                {{ __('tardis::settings.validated') }}
                                            </span>
                                        @endif
                                        <button
                                            wire:click="cloneSetting('{{ $setting['fullKey'] }}')"
                                            class="btn btn-ghost btn-xs text-info"
                                            title="{{ __('tardis::settings.clone_setting') }}"
                                        >
                                            <x-tardis::icon name="document-text" class="w-3 h-3" />
                                        </button>
                                        <button
                                            wire:click="confirmDelete('{{ $setting['fullKey'] }}')"
                                            class="btn btn-ghost btn-xs text-error"
                                            title="{{ __('tardis::settings.delete_setting') }}"
                                        >
                                            <x-tardis::icon name="x-mark" class="w-3 h-3" />
                                        </button>
                                    </div>
                                    @if ($setting['info'])
                                        <p class="text-xs text-base-content/50 mt-0.5">{{ $setting['info'] }}</p>
                                    @endif
                                </div>

                                <!-- Input by Type -->
                                <div class="w-full max-w-xl">
                                    {{-- Text / Email --}}
                                    @if ($setting['type'] === 'text' || $setting['type'] === 'email')
                                        <input
                                            type="{{ $setting['type'] }}"
                                            id="setting-{{ $setting['uuid'] }}"
                                            wire:model="values.{{ $activeGroup }}.{{ $setting['key'] }}"
                                            placeholder="{{ $setting['options']['placeholder'] ?? '' }}"
                                            class="input w-full"
                                        />

                                    {{-- Color --}}
                                    @elseif ($setting['type'] === 'color')
                                        <div class="flex items-center gap-3">
                                            <input
                                                type="color"
                                                id="setting-{{ $setting['uuid'] }}"
                                                wire:model="values.{{ $activeGroup }}.{{ $setting['key'] }}"
                                                class="p-1 w-12 h-10 rounded border border-base-300 cursor-pointer"
                                            />
                                            <span class="text-sm font-mono text-base-content/60">
                                                {{ $this->values[$activeGroup][$setting['key']] ?? '' }}
                                            </span>
                                        </div>

                                    {{-- Textarea --}}
                                    @elseif ($setting['type'] === 'textarea')
                                        <textarea
                                            id="setting-{{ $setting['uuid'] }}"
                                            wire:model="values.{{ $activeGroup }}.{{ $setting['key'] }}"
                                            class="textarea w-full"
                                            rows="{{ $setting['options']['rows'] ?? 4 }}"
                                            placeholder="{{ $setting['options']['placeholder'] ?? '' }}"
                                        ></textarea>

                                    {{-- Number --}}
                                    @elseif ($setting['type'] === 'number')
                                        <input
                                            type="number"
                                            id="setting-{{ $setting['uuid'] }}"
                                            wire:model="values.{{ $activeGroup }}.{{ $setting['key'] }}"
                                            min="{{ $setting['options']['min'] ?? '' }}"
                                            max="{{ $setting['options']['max'] ?? '' }}"
                                            class="input w-full"
                                        />

                                    {{-- Password --}}
                                    @elseif ($setting['type'] === 'password')
                                        <input
                                            type="password"
                                            id="setting-{{ $setting['uuid'] }}"
                                            wire:model="values.{{ $activeGroup }}.{{ $setting['key'] }}"
                                            class="input w-full"
                                        />

                                    {{-- Select --}}
                                    @elseif ($setting['type'] === 'select')
                                        <select
                                            id="setting-{{ $setting['uuid'] }}"
                                            wire:model="values.{{ $activeGroup }}.{{ $setting['key'] }}"
                                            class="select w-full"
                                        >
                                            @if (!empty($setting['options']))
                                                @foreach ($setting['options'] as $optionValue => $optionLabel)
                                                    <option value="{{ $optionValue }}">{{ $optionLabel }}</option>
                                                @endforeach
                                            @endif
                                        </select>

                                    {{-- Toggle (Switch) --}}
                                    @elseif ($setting['type'] === 'toggle')
                                        <label class="inline-flex items-center gap-3 cursor-pointer">
                                            <input
                                                type="checkbox"
                                                id="setting-{{ $setting['uuid'] }}"
                                                wire:model="values.{{ $activeGroup }}.{{ $setting['key'] }}"
                                                class="toggle toggle-primary"
                                                value="1"
                                            />
                                            <span class="text-sm {{ ($this->values[$activeGroup][$setting['key']] ?? false) ? 'text-success font-medium' : 'text-base-content/40' }}">
                                                {{ ($this->values[$activeGroup][$setting['key']] ?? false) ? 'Enabled' : 'Disabled' }}
                                            </span>
                                        </label>

                                    {{-- Date --}}
                                    @elseif ($setting['type'] === 'date')
                                        <input
                                            type="date"
                                            id="setting-{{ $setting['uuid'] }}"
                                            wire:model="values.{{ $activeGroup }}.{{ $setting['key'] }}"
                                            class="input w-full"
                                        />

                                    {{-- Simple Array --}}
                                    @elseif ($setting['type'] === 'simple_array')
                                        <div class="space-y-2">
                                            @php $arrIndex = 0; @endphp
                                            @foreach ((array) ($this->values[$activeGroup][$setting['key']] ?? []) as $arrIndex => $item)
                                                <div class="flex gap-2 items-center">
                                                    <input
                                                        type="text"
                                                        wire:model="values.{{ $activeGroup }}.{{ $setting['key'] }}.{{ $arrIndex }}"
                                                        class="input flex-1 input-sm"
                                                        placeholder="{{ __('tardis::settings.item_n', ['number' => $arrIndex + 1]) }}"
                                                    />
                                                    <button
                                                        wire:click="removeSimpleArrayItem('{{ $setting['fullKey'] }}', {{ $arrIndex }})"
                                                        class="btn btn-ghost btn-square btn-sm text-error"
                                                        title="{{ __('tardis::settings.remove_item') }}"
                                                    >
                                                        <x-tardis::icon name="x-mark" class="w-4 h-4" />
                                                    </button>
                                                </div>
                                            @endforeach
                                            <button
                                                wire:click="addSimpleArrayItem('{{ $setting['fullKey'] }}')"
                                                class="btn btn-ghost btn-sm gap-1 text-primary"
                                            >
                                                <x-tardis::icon name="plus" class="w-4 h-4" />
                                                {{ __('tardis::settings.add_item') }}
                                            </button>
                                        </div>

                                    {{-- Dynamic Input (Key-Value) --}}
                                    @elseif ($setting['type'] === 'dynamic_input')
                                        <div class="space-y-2">
                                            @php $dynIndex = 0; @endphp
                                            @foreach ((array) ($this->values[$activeGroup][$setting['key']] ?? []) as $dynIndex => $row)
                                                <div class="flex gap-2 items-center">
                                                    <input
                                                        type="text"
                                                        wire:model="values.{{ $activeGroup }}.{{ $setting['key'] }}.{{ $dynIndex }}.key"
                                                        placeholder="{{ __('tardis::settings.key') }}"
                                                        class="input w-2/5 input-sm"
                                                    />
                                                    <input
                                                        type="text"
                                                        wire:model="values.{{ $activeGroup }}.{{ $setting['key'] }}.{{ $dynIndex }}.value"
                                                        placeholder="{{ __('tardis::settings.value') }}"
                                                        class="input flex-1 input-sm"
                                                    />
                                                    <button
                                                        wire:click="removeDynamicRow('{{ $setting['fullKey'] }}', {{ $dynIndex }})"
                                                        class="btn btn-ghost btn-square btn-sm text-error"
                                                        title="{{ __('tardis::settings.remove_row') }}"
                                                    >
                                                        <x-tardis::icon name="x-mark" class="w-4 h-4" />
                                                    </button>
                                                </div>
                                            @endforeach
                                            <button
                                                wire:click="addDynamicRow('{{ $setting['fullKey'] }}')"
                                                class="btn btn-ghost btn-sm gap-1 text-primary"
                                            >
                                                <x-tardis::icon name="plus" class="w-4 h-4" />
                                                {{ __('tardis::settings.add_row') }}
                                            </button>
                                        </div>

                                    {{-- Media Picker / Image --}}
                                    @elseif ($setting['type'] === 'media_picker' || $setting['type'] === 'image')
                                        <div class="flex gap-2 items-center">
                                            <input
                                                type="text"
                                                id="setting-{{ $setting['uuid'] }}"
                                                wire:model="values.{{ $activeGroup }}.{{ $setting['key'] }}"
                                                placeholder="{{ __('tardis::settings.media_path_or_url') }}"
                                                class="input flex-1"
                                            />
                                            <button class="btn btn-outline btn-square" title="{{ __('tardis::settings.browse_media') }}">
                                                <x-tardis::icon name="folder" class="w-4 h-4" />
                                            </button>
                                        </div>

                                    {{-- File --}}
                                    @elseif ($setting['type'] === 'file')
                                        <div class="flex gap-2 items-center">
                                            <input
                                                type="text"
                                                id="setting-{{ $setting['uuid'] }}"
                                                wire:model="values.{{ $activeGroup }}.{{ $setting['key'] }}"
                                                placeholder="{{ __('tardis::settings.file_path') }}"
                                                class="input flex-1"
                                            />
                                            <button class="btn btn-outline btn-square" title="{{ __('tardis::settings.browse_files') }}">
                                                <x-tardis::icon name="folder" class="w-4 h-4" />
                                            </button>
                                        </div>

                                    {{-- Fallback --}}
                                    @else
                                        <input
                                            type="text"
                                            id="setting-{{ $setting['uuid'] }}"
                                            wire:model="values.{{ $activeGroup }}.{{ $setting['key'] }}"
                                            placeholder="{{ $setting['options']['placeholder'] ?? '' }}"
                                            class="input w-full"
                                        />
                                    @endif

                                    <!-- Validation hints -->
                                    @if (!empty($setting['validation']))
                                        <div class="flex flex-wrap gap-1 mt-1.5">
                                            @foreach ($setting['validation'] as $rule)
                                                @php
                                                    $ruleLabel = is_string($rule) ? $rule : (is_array($rule) ? key($rule) : '');
                                                    $ruleParam = is_array($rule) ? reset($rule) : null;
                                                @endphp
                                                <span class="badge badge-soft badge-info badge-xs">
                                                    {{ $ruleLabel }}{{ $ruleParam !== null ? ': ' . $ruleParam : '' }}
                                                </span>
                                            @endforeach
                                        </div>
                                    @endif

                                    @error('values.' . $setting['fullKey'])
                                        <label class="label">
                                            <span class="text-error flex items-center gap-1">
                                                <x-tardis::icon name="x-circle" class="w-3.5 h-3.5" />
                                                {{ $message }}
                                            </span>
                                        </label>
                                    @enderror
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <!-- Group Save -->
                    <div class="card-actions justify-end mt-6 pt-4 border-t border-base-200">
                        <button wire:click="save" class="btn btn-primary gap-2">
                            <x-tardis::icon name="check" class="w-4 h-4" />
                            {{ __('tardis::settings.save_group', ['group' => $groups[$activeGroup]['label']]) }}
                        </button>
                    </div>
                </div>
            </div>
        @endif
    @else
        <!-- Empty State -->
        <div class="card bg-base-100 border border-base-300">
            <div class="card-body text-center py-16">
                <x-tardis::icon name="cog-6-tooth" class="w-16 h-16 mx-auto text-base-content/20" />
                @if ($search)
                    <h3 class="text-lg font-semibold mt-4">{{ __('tardis::settings.no_matching_settings') }}</h3>
                    <p class="text-base-content/60 mt-1 max-w-md mx-auto">
                        {{ __('tardis::settings.no_match', ['query' => $search]) }}
                    </p>
                    <button wire:click="$set('search', '')" class="btn btn-ghost btn-sm mt-4">
                        {{ __('tardis::settings.clear_search') }}
                    </button>
                @elseif ($activeGroup && isset($groups[$activeGroup]))
                    <h3 class="text-lg font-semibold mt-4">{{ __('tardis::settings.no_settings_in_this_group') }}</h3>
                    <p class="text-base-content/60 mt-1 max-w-md mx-auto">
                        {{ __('tardis::settings.add_to_group', ['group' => $groups[$activeGroup]['label']]) }}
                    </p>
                    <button wire:click="$set('showAddModal', true)" class="btn btn-primary gap-2 mt-4">
                        <x-tardis::icon name="plus" class="w-4 h-4" />
                        {{ __('tardis::settings.add_setting') }}
                    </button>
                @else
                    <h3 class="text-lg font-semibold mt-4">{{ __('tardis::settings.no_settings_configured') }}</h3>
                    <p class="text-base-content/60 mt-1 max-w-md mx-auto">
                        {{ __('tardis::settings.publish_the_default_settings_preset_or_42ad') }}
                    </p>
                    <button wire:click="$set('showAddModal', true)" class="btn btn-primary gap-2 mt-4">
                        <x-tardis::icon name="plus" class="w-4 h-4" />
                        {{ __('tardis::settings.add_setting') }}
                    </button>
                @endif
            </div>
        </div>
    @endif

    <!-- Add Setting Modal -->
    @if ($showAddModal)
        <dialog class="modal modal-open">
            <div class="modal-box w-full max-w-lg">
                <h3 class="font-bold text-lg mb-4">{{ __('tardis::settings.add_new_setting') }}</h3>

                <form wire:submit="createSetting" class="space-y-4">
                    <div class="flex flex-col gap-2">
                        <label class="label">
                            <span class="text-base-content">{{ __('tardis::settings.key') }} <span class="text-error">*</span></span>
                        </label>
                        <input type="text" wire:model="newKey" class="input" placeholder="{{ __('tardis::settings.e_g_site_name') }}" />
                        @error('newKey')
                            <label class="label">
                                <span class="text-error">{{ $message }}</span>
                            </label>
                        @enderror
                    </div>

                    <div class="flex flex-col gap-2">
                        <label class="label">
                            <span class="text-base-content">{{ __('tardis::settings.group') }}</span>
                        </label>
                        <input type="text" wire:model="newGroup" class="input" placeholder="{{ __('tardis::settings.e_g_admin_optional') }}" />
                    </div>

                    <div class="flex flex-col gap-2">
                        <label class="label">
                            <span class="text-base-content">{{ __('tardis::settings.type') }} <span class="text-error">*</span></span>
                        </label>
                        <select wire:model="newType" class="select">
                            @foreach ($this->getAvailableTypes() as $typeValue => $typeLabel)
                                <option value="{{ $typeValue }}">{{ $typeLabel }}</option>
                            @endforeach
                        </select>
                        @error('newType')
                            <label class="label">
                                <span class="text-error">{{ $message }}</span>
                            </label>
                        @enderror
                    </div>

                    <div class="flex flex-col gap-2">
                        <label class="label">
                            <span class="text-base-content">{{ __('tardis::settings.label') }} <span class="text-error">*</span></span>
                        </label>
                        <input type="text" wire:model="newName" class="input" placeholder="{{ __('tardis::settings.e_g_site_name_2') }}" />
                        @error('newName')
                            <label class="label">
                                <span class="text-error">{{ $message }}</span>
                            </label>
                        @enderror
                    </div>

                    <div class="flex flex-col gap-2">
                        <label class="label">
                            <span class="text-base-content">{{ __('tardis::settings.description') }}</span>
                        </label>
                        <input type="text" wire:model="newInfo" class="input" placeholder="{{ __('tardis::settings.optional_description') }}" />
                    </div>

                    <div class="flex flex-col gap-2">
                        <label class="label">
                            <span class="text-base-content">{{ __('tardis::settings.default_value') }}</span>
                        </label>
                        <input type="text" wire:model="newDefaultValue" class="input" placeholder="{{ __('tardis::settings.optional_default_value') }}" />
                    </div>

                    <div class="flex flex-col gap-2">
                        <label class="label">
                            <span class="text-base-content">{{ __('tardis::settings.validation_rules') }}</span>
                        </label>
                        <input type="text" wire:model="newValidation" class="input" placeholder="{{ __('tardis::settings.e_g_required_string_max_255') }}" />
                        <label class="label">
                            <span class="text-base-content/50">{{ __('tardis::settings.pipe_separated_rules_optional') }}</span>
                        </label>
                    </div>

                    <div class="modal-action">
                        <button type="button" wire:click="$set('showAddModal', false)" class="btn btn-ghost">{{ __('tardis::settings.cancel') }}</button>
                        <button type="submit" class="btn btn-primary">{{ __('tardis::settings.create_setting') }}</button>
                    </div>
                </form>
            </div>
            <form method="dialog" class="modal-backdrop">
                <button wire:click="$set('showAddModal', false)">{{ __('tardis::settings.close') }}</button>
            </form>
        </dialog>
    @endif

    <!-- Delete Confirmation Modal -->
    @if ($showDeleteModal)
        <dialog class="modal modal-open">
            <div class="modal-box">
                <h3 class="font-bold text-lg">{{ __('tardis::settings.delete_setting_2') }}</h3>
                <p class="py-4">{!! __('tardis::settings.confirm_delete', ['key' => '<strong>'.e($deleteKey).'</strong>']) !!}</p>
                <div class="modal-action">
                    <button wire:click="cancelDelete" class="btn btn-ghost">{{ __('tardis::settings.cancel') }}</button>
                    <button wire:click="deleteSetting" class="btn btn-error">{{ __('tardis::settings.delete') }}</button>
                </div>
            </div>
            <form method="dialog" class="modal-backdrop">
                <button wire:click="cancelDelete">{{ __('tardis::settings.close') }}</button>
            </form>
        </dialog>
    @endif

    <!-- Import Modal -->
    @if ($showImportModal)
        <dialog class="modal modal-open">
            <div class="modal-box w-full max-w-lg">
                <h3 class="font-bold text-lg mb-4">{{ __('tardis::settings.import_settings') }}</h3>

                <form wire:submit="importSettings" class="space-y-4">
                    <div class="flex flex-col gap-2">
                        <label class="label">
                            <span class="text-base-content">{{ __('tardis::settings.json_data') }} <span class="text-error">*</span></span>
                        </label>
                        <textarea
                            wire:model="importJson"
                            class="textarea font-mono text-sm"
                            rows="10"
                            placeholder='[{"key": "setting_name", "type": "text", "name": "Setting Name", "value": "default"}]'
                        ></textarea>
                        @error('importJson')
                            <label class="label">
                                <span class="text-error">{{ $message }}</span>
                            </label>
                        @enderror
                        @if ($importError)
                            <label class="label">
                                <span class="text-error" role="alert">{{ $importError }}</span>
                            </label>
                        @endif
                    </div>

                    <div class="modal-action">
                        <button type="button" wire:click="$set('showImportModal', false)" class="btn btn-ghost">{{ __('tardis::settings.cancel') }}</button>
                        <button type="submit" class="btn btn-primary">{{ __('tardis::settings.import') }}</button>
                    </div>
                </form>
            </div>
            <form method="dialog" class="modal-backdrop">
                <button wire:click="$set('showImportModal', false)">{{ __('tardis::settings.close') }}</button>
            </form>
        </dialog>
    @endif

    <!-- Export Modal -->
    @if ($showExportModal)
        <dialog class="modal modal-open">
            <div class="modal-box w-full max-w-lg">
                <h3 class="font-bold text-lg mb-4">{{ __('tardis::settings.export_settings') }}</h3>

                <div class="flex flex-col gap-2">
                    <label class="label">
                        <span class="text-base-content">{{ __('tardis::settings.json_data') }}</span>
                    </label>
                    <textarea
                        class="textarea font-mono text-sm"
                        rows="10"
                        readonly
                    >{{ $exportJson }}</textarea>
                </div>

                <div class="modal-action">
                    <button type="button" wire:click="$set('showExportModal', false)" class="btn btn-ghost">{{ __('tardis::settings.close_2') }}</button>
                    <button type="button" onclick="navigator.clipboard.writeText(document.querySelector('[wire\\\\:model=exportJson]').value || document.querySelector('textarea[readonly]').value)" class="btn btn-primary">{{ __('tardis::settings.copy_to_clipboard') }}</button>
                </div>
            </div>
            <form method="dialog" class="modal-backdrop">
                <button wire:click="$set('showExportModal', false)">{{ __('tardis::settings.close') }}</button>
            </form>
        </dialog>
    @endif

    <!-- Add Group Modal -->
    @if ($showAddGroupModal)
        <dialog class="modal modal-open">
            <div class="modal-box">
                <h3 class="font-bold text-lg mb-4">{{ __('tardis::settings.add_group') }}</h3>
                <form wire:submit="addGroup" class="space-y-4">
                    <div class="flex flex-col gap-2">
                        <label class="label">
                            <span class="text-base-content">{{ __('tardis::settings.group_name') }}</span>
                        </label>
                        <input type="text" wire:model="newGroupName" class="input" placeholder="{{ __('tardis::settings.e_g_general_media_auth') }}" autofocus />
                        @error('newGroupName')
                            <label class="label">
                                <span class="text-error">{{ $message }}</span>
                            </label>
                        @enderror
                    </div>
                </form>
                <div class="modal-action">
                    <button wire:click="$set('showAddGroupModal', false)" class="btn btn-ghost">{{ __('tardis::settings.cancel') }}</button>
                    <button wire:click="addGroup" class="btn btn-primary">{{ __('tardis::settings.create_group') }}</button>
                </div>
            </div>
            <form method="dialog" class="modal-backdrop">
                <button wire:click="$set('showAddGroupModal', false)">{{ __('tardis::settings.close') }}</button>
            </form>
        </dialog>
    @endif

    <!-- JSON Output Panel -->
    <div class="mt-6">
        <div x-data="{ open: false }">
            <button @click="open = !open" class="btn btn-ghost btn-sm gap-2 w-full justify-between">
                <span class="flex items-center gap-2">
                    <x-tardis::icon name="document-text" class="w-4 h-4" />
                    {{ __('tardis::settings.json_output') }}
                </span>
                <x-tardis::icon name="chevron-up-down" class="w-4 h-4 transition-transform" x-bind:class="{ 'rotate-180': open }" />
            </button>
            <div x-show="open" x-collapse class="mt-2">
                <div class="card bg-base-200 border border-base-300">
                    <div class="card-body p-4">
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-xs text-base-content/50">{{ __('tardis::settings.settings_json') }}</span>
                            <button onclick="navigator.clipboard.writeText(document.getElementById('json-output').textContent)" class="btn btn-ghost btn-xs">
                                {{ __('tardis::settings.copy') }}
                            </button>
                        </div>
                        <pre id="json-output" class="text-xs font-mono overflow-auto max-h-64 p-3 bg-base-300 rounded">{{ $this->getJsonOutput() }}</pre>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>