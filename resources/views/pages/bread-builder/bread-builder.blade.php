@php
    $fieldGroups = [
        __('tardis::builder.groups.text') => ['text', 'textarea', 'markdown', 'code_editor', 'slug', 'password', 'color', 'hidden'],
        __('tardis::builder.groups.numbers') => ['number', 'slider'],
        __('tardis::builder.groups.choice') => ['select', 'radio', 'checkbox', 'toggle', 'tags'],
        __('tardis::builder.groups.date_time') => ['date', 'datetime', 'time'],
        __('tardis::builder.groups.file') => ['file', 'media_picker'],
        __('tardis::builder.groups.relations') => ['belongs_to_many', 'has_many'],
    ];

    $slugBadges = [
        'available' => ['badge-success', 'Available'],
        'current' => ['badge-info', __('tardis::builder.slug_status.current')],
        'taken' => ['badge-error', __('tardis::builder.slug_status.taken')],
        'invalid' => ['badge-warning', __('tardis::builder.slug_status.invalid')],
        'reserved' => ['badge-error', __('tardis::builder.slug_status.reserved')],
        'empty' => ['badge-ghost', __('tardis::builder.slug_status.empty')],
    ];
@endphp

<div x-data="builderLayout()">
    <x-tardis::page-header
        :title="$editMode ? __('tardis::builder.edit_bread') : __('tardis::builder.create_bread')"
        :description="$editMode ? __('tardis::builder.modify_definition') : __('tardis::builder.define_new_resource')"
    >
        <x-slot:action>
            <label class="label cursor-pointer justify-start gap-2">
                <input type="checkbox" wire:model.live="focusMode" class="toggle toggle-primary toggle-sm" />
                <span class="text-base-content">{{ __('tardis::builder.focus_mode') }}</span>
            </label>
        </x-slot:action>
    </x-tardis::page-header>

    @if (session('error'))
        <div class="alert alert-error mb-4">
            <x-tardis::icon name="exclamation-triangle" class="w-5 h-5" />
            <span>{{ session('error') }}</span>
        </div>
    @endif

    @if ($errors->any())
        <div class="alert alert-error mb-4">
            <x-tardis::icon name="exclamation-triangle" class="w-5 h-5" />
            <div>
                <p class="font-semibold">{{ __('tardis::builder.please_fix_the_following_before_saving') }}</p>
                <ul class="list-disc list-inside text-sm">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        </div>
    @endif

    @if ($focusMode)
        <div class="alert mb-4 border-primary/30 bg-primary/5">
            <x-tardis::icon name="sparkles" class="w-5 h-5" />
            <span class="text-sm">{{ __('tardis::builder.focus_mode_hides_read_only_hints_854f') }}</span>
        </div>
    @endif

    <!-- Steps -->
    <ul class="steps steps-horizontal w-full mb-6">
        @foreach ([1 => __('tardis::builder.steps.model'), 2 => __('tardis::builder.steps.fields'), 3 => __('tardis::builder.steps.configure'), 4 => __('tardis::builder.steps.review')] as $index => $label)
            <li wire:click="goToStep({{ $index }})" class="step cursor-pointer {{ $step >= $index ? 'step-primary' : '' }}">
                {{ $label }}
            </li>
        @endforeach
    </ul>

    <!-- Step 1: Select Model -->
    @if ($step === 1)
        <div class="card bg-base-100 border border-base-300">
            <div class="card-body">
                <h2 class="card-title">{{ __('tardis::builder.step_1_select_model') }}</h2>
                <p class="text-base-content/60">{{ __('tardis::builder.choose_an_eloquent_model_to_create_e1d4') }}</p>

                <div class="flex flex-col gap-2 mt-4">
                    <label class="label">
                        <span class="text-base-content">{{ __('tardis::builder.model_class') }}</span>
                    </label>
                    <select wire:model.change.live="model" class="select w-full">
                        <option value="">{{ __('tardis::builder.select_a_model') }}</option>
                        @foreach ($this->getModelOptions() as $class => $name)
                            <option value="{{ $class }}">{{ $name }}</option>
                        @endforeach
                    </select>
                    @if (! $focusMode)
                        <label class="label">
                            <span class="text-base-content/50">
                                {!! __('tardis::builder.fields_from_fillable', ['property' => '<code>$fillable</code>']) !!}
                            </span>
                        </label>
                    @endif
                </div>

                @if ($model !== '' && $modelTable !== '')
                    <div class="stats stats-vertical sm:stats-horizontal mt-4 bg-base-200">
                        <div class="stat py-3">
                            <div class="stat-title text-xs">{{ __('tardis::builder.table') }}</div>
                            <div class="stat-value text-lg">{{ $modelTable }}</div>
                        </div>
                        <div class="stat py-3">
                            <div class="stat-title text-xs">{{ __('tardis::builder.timestamps') }}</div>
                            <div class="stat-value text-lg">{{ $modelHasTimestamps ? 'Yes' : 'No' }}</div>
                        </div>
                        <div class="stat py-3">
                            <div class="stat-title text-xs">{{ __('tardis::builder.soft_deletes') }}</div>
                            <div class="stat-value text-lg">{{ $modelHasSoftDeletes ? 'Yes' : 'No' }}</div>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    @endif

    <!-- Step 2: Review Fields & Relationships -->
    @if ($step === 2)
        <div class="card bg-base-100 border border-base-300">
            <div class="card-body">
                <div class="tabs tabs-box mb-4">
                    <button wire:click="$set('activeTab', 'fields')" role="tab" class="tab {{ ($activeTab ?? 'fields') === 'fields' ? 'tab-active' : '' }}">
                        {{ __('tardis::builder.fields') }}
                        <span class="badge badge-sm badge-ghost ml-1">{{ count($fieldConfig) }}</span>
                    </button>
                    <button wire:click="$set('activeTab', 'relationships')" role="tab" class="tab {{ ($activeTab ?? '') === 'relationships' ? 'tab-active' : '' }}">
                        {{ __('tardis::builder.relationships') }}
                        <span class="badge badge-sm badge-ghost ml-1">{{ count($relationshipConfig) }}</span>
                    </button>
                </div>

                @if (($activeTab ?? 'fields') === 'fields')
                    <div class="flex flex-wrap items-end justify-between gap-3">
                        <div>
                            <h2 class="card-title">{{ __('tardis::builder.detected_fields') }}</h2>
                            <p class="text-base-content/60">{{ __('tardis::builder.review_and_configure_the_detected_fields') }}</p>
                        </div>

                        <label class="input input-sm flex items-center gap-2 w-full sm:w-64">
                            <x-tardis::icon name="magnifying-glass" class="w-4 h-4 text-base-content/60" />
                            <input type="text" wire:model.live.debounce.300ms="fieldSearch" class="grow" placeholder="{{ __('tardis::builder.filter_fields') }}" />
                        </label>
                    </div>

                    <div class="flex flex-wrap items-center gap-2 mt-4 p-3 rounded-box bg-base-200">
                        <span class="text-xs font-semibold uppercase tracking-wide text-base-content/60">{{ __('tardis::builder.set_all') }}</span>
                        @foreach (['browse' => 'B', 'read' => 'R', 'edit' => 'E', 'add' => 'A'] as $flag => $letter)
                            <button wire:click="toggleAllFields('{{ $flag }}', true)" class="btn btn-xs btn-outline">
                                {{ $letter }} on
                            </button>
                            <button wire:click="toggleAllFields('{{ $flag }}', false)" class="btn btn-xs btn-ghost">
                                {{ __('tardis::builder.all_off', ['flag' => $letter]) }}
                            </button>
                        @endforeach
                    </div>

                    <div class="overflow-x-auto mt-4">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th class="w-10" aria-hidden="true"></th>
                                    <th scope="col">{{ __('tardis::builder.field') }}</th>
                                    <th scope="col">{{ __('tardis::builder.type') }}</th>
                                    <th class="text-center" scope="col">{{ __('tardis::builder.browse') }}</th>
                                    <th class="text-center" scope="col">{{ __('tardis::builder.read') }}</th>
                                    <th class="text-center" scope="col">{{ __('tardis::builder.edit') }}</th>
                                    <th class="text-center" scope="col">{{ __('tardis::builder.add') }}</th>
                                    @unless ($focusMode)
                                        <th scope="col">{{ __('tardis::builder.validation') }}</th>
                                    @endunless
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($this->visibleFieldKeys as $position => $key)
                                    @php $field = $fieldConfig[$key]; @endphp
                                    <tr wire:key="field-{{ $key }}">
                                        <td>
                                            <div class="join join-vertical">
                                                <button wire:click="moveField('{{ $key }}', -1)" class="btn join-item btn-ghost btn-xs px-1" title="{{ __('tardis::builder.move_up') }}">
                                                    <x-tardis::icon name="chevron-up" class="w-3 h-3" />
                                                </button>
                                                <button wire:click="moveField('{{ $key }}', 1)" class="btn join-item btn-ghost btn-xs px-1" title="{{ __('tardis::builder.move_down') }}">
                                                    <x-tardis::icon name="chevron-down" class="w-3 h-3" />
                                                </button>
                                            </div>
                                        </td>
                                        <td class="font-medium">
                                            {{ $field['label'] ?? $key }}
                                            <span class="block text-xs font-normal text-base-content/50">{{ $key }}</span>
                                        </td>
                                        <td>
                                            <select wire:model.live="fieldConfig.{{ $key }}.type" class="select select-xs">
                                                @foreach ($fieldGroups as $group => $types)
                                                    <optgroup label="{{ $group }}">
                                                        @foreach ($types as $type)
                                                            <option value="{{ $type }}">{{ Str::headline($type) }}</option>
                                                        @endforeach
                                                    </optgroup>
                                                @endforeach
                                            </select>
                                        </td>
                                        <td class="text-center"><input type="checkbox" wire:model.live="fieldConfig.{{ $key }}.browse" class="checkbox checkbox-sm" /></td>
                                        <td class="text-center"><input type="checkbox" wire:model.live="fieldConfig.{{ $key }}.read" class="checkbox checkbox-sm" /></td>
                                        <td class="text-center"><input type="checkbox" wire:model.live="fieldConfig.{{ $key }}.edit" class="checkbox checkbox-sm" /></td>
                                        <td class="text-center"><input type="checkbox" wire:model.live="fieldConfig.{{ $key }}.add" class="checkbox checkbox-sm" /></td>
                                        @unless ($focusMode)
                                            <td>
                                                <div class="flex flex-wrap gap-1">
                                                    @foreach (['required', 'email', 'string', 'integer', 'numeric', 'unique', 'max'] as $rule)
                                                        <label class="cursor-pointer">
                                                            <input type="checkbox" wire:model.live="fieldConfig.{{ $key }}.validation" value="{{ $rule }}" class="checkbox checkbox-xs" />
                                                            <span class="text-[10px]">{{ $rule }}</span>
                                                        </label>
                                                    @endforeach
                                                </div>
                                            </td>
                                        @endunless
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="{{ $focusMode ? 8 : 9 }}" class="text-center py-8 text-base-content/50">
                                            @if ($fieldSearch !== '')
                                                {{ __('tardis::builder.no_fields_match', ['query' => $fieldSearch]) }}
                                            @else
                                                {!! __('tardis::builder.no_fields_detected', ['property' => '<code>$fillable</code>']) !!}
                                            @endif
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                @else
                    <h2 class="card-title">{{ __('tardis::builder.detected_relationships') }}</h2>
                    <p class="text-base-content/60">{{ __('tardis::builder.configure_how_relationships_are_displaye_2234') }}</p>

                    @if (empty($relationshipConfig))
                        <div class="text-center py-8 text-base-content/50">
                            <p>{{ __('tardis::builder.no_relationships_detected_in_this_model') }}</p>
                        </div>
                    @else
                        <div class="overflow-x-auto mt-4">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th scope="col">{{ __('tardis::builder.relation') }}</th>
                                        <th scope="col">{{ __('tardis::builder.type') }}</th>
                                        <th scope="col">{{ __('tardis::builder.related_model') }}</th>
                                        <th scope="col">{{ __('tardis::builder.display_type') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($relationshipConfig as $name => $rel)
                                        <tr wire:key="rel-{{ $name }}">
                                            <td class="font-medium">{{ $name }}</td>
                                            <td><span class="badge badge-ghost badge-sm">{{ $rel['type'] }}</span></td>
                                            <td>{{ class_basename($rel['model']) }}</td>
                                            <td>
                                                <select wire:model.live="relationshipConfig.{{ $name }}.display_type" class="select select-xs">
                                                    <option value="select">{{ __('tardis::builder.select') }}</option>
                                                    <option value="checkbox">{{ __('tardis::builder.checkbox') }}</option>
                                                    <option value="table">{{ __('tardis::builder.table') }}</option>
                                                    <option value="hidden">{{ __('tardis::builder.hidden') }}</option>
                                                </select>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                @endif
            </div>
        </div>
    @endif

    <!-- Step 3: Configure -->
    @if ($step === 3)
        <div class="card bg-base-100 border border-base-300">
            <div class="card-body">
                <div class="tabs tabs-box mb-4">
                    <button wire:click="$set('activeTab', 'general')" role="tab" class="tab {{ ($activeTab ?? 'general') === 'general' ? 'tab-active' : '' }}">{{ __('tardis::builder.general') }}</button>
                    <button wire:click="$set('activeTab', 'browse-layout')" role="tab" class="tab {{ ($activeTab ?? '') === 'browse-layout' ? 'tab-active' : '' }}">{{ __('tardis::builder.browse_layout') }}</button>
                    <button wire:click="$set('activeTab', 'read-layout')" role="tab" class="tab {{ ($activeTab ?? '') === 'read-layout' ? 'tab-active' : '' }}">{{ __('tardis::builder.read_layout') }}</button>
                    <button wire:click="$set('activeTab', 'edit-layout')" role="tab" class="tab {{ ($activeTab ?? '') === 'edit-layout' ? 'tab-active' : '' }}">{{ __('tardis::builder.edit_layout') }}</button>
                </div>

                @if (($activeTab ?? 'general') === 'general')
                    <h2 class="card-title">{{ __('tardis::builder.general_settings') }}</h2>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                        <div class="flex flex-col gap-2">
                            <label class="label">
                                <span class="text-base-content">{{ __('tardis::builder.slug_url') }}</span>
                                @php [$slugClass, $slugLabel] = $slugBadges[$this->slugStatus]; @endphp
                                <span class="badge badge-sm {{ $slugClass }}">{{ $slugLabel }}</span>
                            </label>
                            <input type="text" wire:model="slug" class="input font-mono" placeholder="{{ __('tardis::builder.e_g_posts') }}" />
                            @unless ($focusMode)
                                <label class="label">
                                    <span class="text-base-content/50">
                                        {{ __('tardis::builder.generated_from_the_plural_name_until_09c2') }}
                                    </span>
                                </label>
                            @endunless
                        </div>

                        <div class="flex flex-col gap-2">
                            <label class="label"><span class="text-base-content">{{ __('tardis::builder.name') }}</span></label>
                            <input type="text" wire:model="name" class="input" placeholder="{{ __('tardis::builder.e_g_post') }}" />
                        </div>

                        <div class="flex flex-col gap-2">
                            <label class="label"><span class="text-base-content">{{ __('tardis::builder.name_plural') }}</span></label>
                            <input type="text" wire:model="namePlural" class="input" placeholder="{{ __('tardis::builder.e_g_posts_2') }}" />
                        </div>

                        <div class="flex flex-col gap-2">
                            <label class="label"><span class="text-base-content">{{ __('tardis::builder.icon') }}</span></label>
                            <div class="flex gap-2">
                                <div class="flex-1 flex items-center gap-2 input">
                                    <x-tardis::icon :name="$icon ?: 'swatch'" class="w-4 h-4 text-base-content/60" />
                                    <span class="truncate font-mono text-sm">{{ $icon ?: 'none' }}</span>
                                </div>
                                <button type="button" wire:click="$set('showIconPicker', true)" class="btn btn-outline">
                                    <x-tardis::icon name="swatch" class="w-4 h-4" />
                                    {{ __('tardis::builder.pick') }}
                                </button>
                                @if ($icon)
                                    <button type="button" wire:click="$set('icon', null)" class="btn btn-ghost" title="{{ __('tardis::builder.clear_icon') }}">
                                        <x-tardis::icon name="x-mark" class="w-4 h-4" />
                                    </button>
                                @endif
                            </div>
                        </div>

                        <div class="flex flex-col gap-2 md:col-span-2">
                            <label class="label"><span class="text-base-content">{{ __('tardis::builder.description') }}</span></label>
                            <textarea wire:model="description" class="textarea" rows="2"></textarea>
                        </div>

                        <div class="flex flex-col gap-2">
                            <label class="label"><span class="text-base-content">{{ __('tardis::builder.search_key') }}</span></label>
                            <input type="text" wire:model="searchKey" class="input" placeholder="{{ __('tardis::builder.field_for_global_search') }}" />
                        </div>

                        <div class="flex flex-col gap-2">
                            <label class="label"><span class="text-base-content">{{ __('tardis::builder.order_column') }}</span></label>
                            <input type="text" wire:model="orderColumn" class="input" placeholder="{{ __('tardis::builder.e_g_created_at') }}" />
                        </div>

                        <div class="flex flex-col gap-2">
                            <label class="label"><span class="text-base-content">{{ __('tardis::builder.order_direction') }}</span></label>
                            <select wire:model="orderDirection" class="select">
                                <option value="asc">{{ __('tardis::builder.ascending') }}</option>
                                <option value="desc">{{ __('tardis::builder.descending') }}</option>
                            </select>
                        </div>

                        <div class="flex flex-col gap-2">
                            <label class="label cursor-pointer justify-start gap-3">
                                <input type="checkbox" wire:model.live="softDelete" class="toggle toggle-primary" />
                                <span class="text-base-content">{{ __('tardis::builder.enable_soft_delete') }}</span>
                            </label>
                            @if ($modelHasSoftDeletes)
                                <label class="label">
                                    <span class="text-warning">{{ __('tardis::builder.this_model_already_uses_softdeletes') }}</span>
                                </label>
                            @else
                                <label class="label">
                                    <span class="text-base-content/50">{{ __('tardis::builder.allow_restoring_deleted_items') }}</span>
                                </label>
                            @endif
                        </div>
                    </div>
                @endif

                @if (($activeTab ?? '') === 'browse-layout')
                    <h2 class="card-title">{{ __('tardis::builder.browse_layout') }}</h2>
                    <p class="text-base-content/60">{{ __('tardis::builder.configure_which_columns_appear_in_the_1952') }}</p>

                    <div class="overflow-x-auto mt-4">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th scope="col">{{ __('tardis::builder.field') }}</th>
                                    <th class="text-center" scope="col">{{ __('tardis::builder.visible') }}</th>
                                    <th class="text-center" scope="col">{{ __('tardis::builder.sortable') }}</th>
                                    <th class="text-center" scope="col">{{ __('tardis::builder.searchable') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($this->orderedFieldKeys as $key)
                                    <tr wire:key="browse-{{ $key }}">
                                        <td class="font-medium">{{ $fieldConfig[$key]['label'] ?? $key }}</td>
                                        <td class="text-center"><input type="checkbox" wire:model.live="browseColumns.{{ $key }}.visible" class="checkbox checkbox-sm" /></td>
                                        <td class="text-center"><input type="checkbox" wire:model.live="browseColumns.{{ $key }}.sortable" class="checkbox checkbox-sm" /></td>
                                        <td class="text-center"><input type="checkbox" wire:model.live="browseColumns.{{ $key }}.searchable" class="checkbox checkbox-sm" /></td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="text-center py-8 text-base-content/50">{{ __('tardis::builder.no_fields_to_lay_out_yet') }}</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                @endif

                @if (($activeTab ?? '') === 'read-layout')
                    <h2 class="card-title">{{ __('tardis::builder.read_layout') }}</h2>
                    <p class="text-base-content/60">{{ __('tardis::builder.choose_the_order_fields_appear_in_1e2c') }}</p>

                    <div class="mt-4 space-y-2">
                        @forelse ($readLayout as $position => $key)
                            <div
                                wire:key="read-{{ $key }}"
                                draggable="true"
                                x-on:dragstart="startDrag('{{ $key }}', {{ $position }})"
                                x-on:dragend="endDrag()"
                                x-on:dragenter.prevent="dragEnter({{ $position }})"
                                x-on:dragover.prevent
                                x-on:drop.prevent="drop({{ $position }}, $wire.readLayout, arr => $wire.$set('readLayout', arr))"
                                :class="dragOver === {{ $position }} ? 'ring-2 ring-primary' : ''"
                                class="p-2 rounded-box bg-base-200 cursor-grab"
                            >
                                <div class="flex items-center gap-3">
                                    <span class="badge badge-neutral badge-sm cursor-grab">{{ $position + 1 }}</span>
                                    <span class="font-medium flex-1">{{ $fieldConfig[$key]['label'] ?? $key }}</span>
                                    @if (! empty($layoutLegends[$key]))
                                        <span class="badge badge-ghost badge-sm gap-1 max-w-40 truncate" title="{{ $layoutLegends[$key] }}">
                                            <x-tardis::icon name="tag" class="w-3 h-3" />
                                            {{ $layoutLegends[$key] }}
                                        </span>
                                    @endif
                                    <button wire:click="moveReadField('{{ $key }}', -1)" class="btn btn-ghost btn-xs px-1" title="{{ __('tardis::builder.move_up') }}">
                                        <x-tardis::icon name="chevron-up" class="w-3 h-3" />
                                    </button>
                                    <button wire:click="moveReadField('{{ $key }}', 1)" class="btn btn-ghost btn-xs px-1" title="{{ __('tardis::builder.move_down') }}">
                                        <x-tardis::icon name="chevron-down" class="w-3 h-3" />
                                    </button>
                                    <button wire:click="openFieldOptions('{{ $key }}')" class="btn btn-ghost btn-xs px-1" title="{{ __('tardis::builder.field_options') }}">
                                        <x-tardis::icon name="cog-6-tooth" class="w-3 h-3" />
                                    </button>
                                    <button wire:click="toggleReadField('{{ $key }}')" class="btn btn-ghost btn-xs text-error" title="{{ __('tardis::builder.remove') }}">
                                        <x-tardis::icon name="x-mark" class="w-3 h-3" />
                                    </button>
                                </div>
                                <div class="flex items-center gap-2 mt-2 pl-1">
                                    <span class="text-[10px] uppercase tracking-wide text-base-content/50">{{ __('tardis::builder.column_width') }}</span>
                                    <div class="flex items-center gap-0.5">
                                        @foreach (range(1, 6) as $span)
                                            <button
                                                type="button"
                                                wire:click="setFieldWidth('{{ $key }}', {{ $span }})"
                                                class="w-5 h-5 rounded text-[10px] font-semibold flex items-center justify-center transition-colors {{ ($layoutWidths[$key] ?? 6) === $span ? 'bg-primary text-primary-content' : 'bg-base-300 text-base-content/60 hover:bg-base-content/25' }}"
                                                title="{{ $span }}/6"
                                            >{{ $span }}</button>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        @empty
                            <p class="text-sm text-base-content/50 py-4 text-center">{{ __('tardis::builder.no_fields_in_the_read_layout') }}</p>
                        @endforelse
                    </div>

                    @if (count($readLayout) < count($this->orderedFieldKeys))
                        <div class="mt-4">
                            <p class="text-xs uppercase tracking-wide text-base-content/60 mb-2">{{ __('tardis::builder.add_fields') }}</p>
                            <div class="flex flex-wrap gap-1">
                                @foreach ($this->orderedFieldKeys as $key)
                                    @continue(in_array($key, $readLayout, true))
                                    <button wire:click="toggleReadField('{{ $key }}')" class="btn btn-xs btn-outline">
                                        <x-tardis::icon name="plus" class="w-3 h-3" />
                                        {{ $fieldConfig[$key]['label'] ?? $key }}
                                    </button>
                                @endforeach
                            </div>
                        </div>
                    @endif
                @endif

                <x-tardis::slide-in :title="__('tardis::builder.field_options')">
                    @if ($drawerField && isset($fieldConfig[$drawerField]))
                        <div class="space-y-4">
                            <div>
                                <label class="label" for="drawer-field-label">{{ __('tardis::builder.field_label') }}</label>
                                <input id="drawer-field-label" type="text" class="input input-bordered w-full" wire:model="fieldConfig.{{ $drawerField }}.label" />
                            </div>
                            <div>
                                <label class="label" for="drawer-field-legend">{{ __('tardis::builder.section_heading') }}</label>
                                <input id="drawer-field-legend" type="text" class="input input-bordered w-full" wire:model="layoutLegends.{{ $drawerField }}" placeholder="{{ __('tardis::builder.section_heading_placeholder') }}" />
                                <p class="text-xs text-base-content/50 mt-1">{{ __('tardis::builder.section_heading_hint') }}</p>
                            </div>
                        </div>
                    @endif
                </x-tardis::slide-in>

                @if (($activeTab ?? '') === 'edit-layout')
                    <h2 class="card-title">{{ __('tardis::builder.edit_layout') }}</h2>
                    <p class="text-base-content/60">{{ __('tardis::builder.configure_tabs_and_field_grouping_for_fee0') }}</p>

                    <div class="mt-4">
                        <div class="flex items-center gap-2 mb-4">
                            <button wire:click="addEditTab" class="btn btn-outline btn-sm gap-1">
                                <x-tardis::icon name="plus" class="w-4 h-4" />
                                {{ __('tardis::builder.add_tab') }}
                            </button>
                        </div>

                        @forelse ($editTabs as $tabIndex => $tab)
                            <div wire:key="tab-{{ $tabIndex }}" class="card bg-base-200 mb-3 border border-base-300">
                                <div class="card-body p-4">
                                    <div class="flex items-center gap-2 mb-3">
                                        <input type="text" wire:model="editTabs.{{ $tabIndex }}.name" class="input input-sm flex-1" placeholder="{{ __('tardis::builder.tab_name') }}" />
                                        <button wire:click="removeEditTab({{ $tabIndex }})" class="btn btn-ghost btn-xs text-error">
                                            <x-tardis::icon name="x-mark" class="w-4 h-4" />
                                        </button>
                                    </div>
                                    <div class="flex flex-wrap gap-2">
                                        @foreach ($this->orderedFieldKeys as $key)
                                            <label class="flex items-center gap-1 cursor-pointer">
                                                <input type="checkbox" wire:model.live="editTabs.{{ $tabIndex }}.fields" value="{{ $key }}" class="checkbox checkbox-sm checkbox-primary" />
                                                <span class="text-xs">{{ $fieldConfig[$key]['label'] ?? $key }}</span>
                                            </label>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        @empty
                            <p class="text-sm text-base-content/50">{{ __('tardis::builder.no_tabs_configured_fields_will_appear_ad40') }}</p>
                        @endforelse
                    </div>
                @endif
            </div>
        </div>
    @endif

    <!-- Step 4: Review & Save -->
    @if ($step === 4)
        <div class="space-y-4">
            @if (count($this->reviewWarnings) > 0)
                <div class="card bg-base-100 border border-base-300">
                    <div class="card-body">
                        <h2 class="card-title text-base">{{ __('tardis::builder.before_you_save') }}</h2>
                        <ul class="space-y-2">
                            @foreach ($this->reviewWarnings as $warning)
                                @php
                                    $styles = [
                                        'error' => ['alert-error', 'exclamation-triangle'],
                                        'warning' => ['alert-warning', 'exclamation-triangle'],
                                        'info' => ['alert-info', 'information-circle'],
                                    ];
                                    [$alertClass, $iconName] = $styles[$warning['level']] ?? $styles['info'];
                                @endphp
                                <li class="alert {{ $alertClass }} py-2">
                                    <x-tardis::icon :name="$iconName" class="w-4 h-4" />
                                    <span class="text-sm">{{ $warning['message'] }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            @endif

            <div class="card bg-base-100 border border-base-300">
                <div class="card-body">
                    <h2 class="card-title">{{ __('tardis::builder.summary') }}</h2>

                    <div class="overflow-x-auto">
                        <table class="table">
                            <tbody>
                                <tr>
                                    <th class="w-48" scope="row">{{ __('tardis::builder.slug') }}</th>
                                    <td class="font-mono">{{ $this->reviewSummary['slug'] ?: '—' }}</td>
                                </tr>
                                <tr>
                                    <th scope="row">{{ __('tardis::builder.model') }}</th>
                                    <td>
                                        {{ class_basename($this->reviewSummary['model'] ?: '') ?: '—' }}
                                        <span class="badge badge-ghost badge-sm ml-2">{{ $this->reviewSummary['table'] ?: 'no table' }}</span>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">{{ __('tardis::builder.names') }}</th>
                                    <td>{{ $this->reviewSummary['name'] ?: '—' }} <span class="text-base-content/50">/</span> {{ $this->reviewSummary['name_plural'] ?: '—' }}</td>
                                </tr>
                                <tr>
                                    <th scope="row">{{ __('tardis::builder.icon') }}</th>
                                    <td>
                                        @if ($this->reviewSummary['icon'])
                                            <x-tardis::icon :name="$this->reviewSummary['icon']" class="w-4 h-4 inline" />
                                            <span class="font-mono text-sm ml-1">{{ $this->reviewSummary['icon'] }}</span>
                                        @else
                                            —
                                        @endif
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">{{ __('tardis::builder.fields') }}</th>
                                    <td>
                                        <span class="badge badge-sm">{{ __('tardis::builder.summary_total', ['count' => $this->reviewSummary['total_fields']]) }}</span>
                                        <span class="badge badge-sm badge-ghost ml-1">{{ __('tardis::builder.summary_browse', ['count' => $this->reviewSummary['browse_fields'] ? count($this->reviewSummary['browse_fields']) : 0]) }}</span>
                                        <span class="badge badge-sm badge-ghost ml-1">{{ __('tardis::builder.summary_read', ['count' => count($this->reviewSummary['read_fields'])]) }}</span>
                                        <span class="badge badge-sm badge-ghost ml-1">{{ __('tardis::builder.summary_add', ['count' => $this->reviewSummary['add_fields']]) }}</span>
                                        <span class="badge badge-sm badge-ghost ml-1">{{ __('tardis::builder.summary_edit', ['count' => $this->reviewSummary['edit_fields']]) }}</span>
                                    </td>
                                </tr>
                                @if ($this->reviewSummary['browse_fields'])
                                    <tr>
                                        <th scope="row">{{ __('tardis::builder.browse_columns') }}</th>
                                        <td class="flex flex-wrap gap-1">
                                            @foreach ($this->reviewSummary['browse_fields'] as $key)
                                                <span class="badge badge-outline badge-sm">{{ $fieldConfig[$key]['label'] ?? $key }}</span>
                                            @endforeach
                                        </td>
                                    </tr>
                                @endif
                                <tr>
                                    <th scope="row">{{ __('tardis::builder.relationships') }}</th>
                                    <td>{{ $this->reviewSummary['relationships'] }}</td>
                                </tr>
                                <tr>
                                    <th scope="row">{{ __('tardis::builder.search_key_2') }}</th>
                                    <td class="font-mono">{{ $this->reviewSummary['search_key'] ?: '—' }}</td>
                                </tr>
                                <tr>
                                    <th scope="row">{{ __('tardis::builder.ordering') }}</th>
                                    <td class="font-mono">
                                        {{ $this->reviewSummary['order_column'] ?: 'none' }}
                                        @if ($this->reviewSummary['order_column'])
                                            <span class="badge badge-ghost badge-sm ml-1">{{ $this->reviewSummary['order_direction'] }}</span>
                                        @endif
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">{{ __('tardis::builder.soft_delete') }}</th>
                                    <td>
                                        @if ($this->reviewSummary['soft_delete'])
                                            <span class="badge badge-success badge-sm">{{ __('tardis::builder.enabled') }}</span>
                                        @else
                                            <span class="badge badge-ghost badge-sm">{{ __('tardis::builder.disabled') }}</span>
                                        @endif
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <!-- Sticky wizard footer -->
    <div class="sticky bottom-0 z-20 mt-6 py-3 bg-base-100/90 backdrop-blur border-t border-base-200">
        <div class="card-actions justify-between">
            <div>
                @if ($step > 1)
                    <button wire:click="goToStep({{ $step - 1 }})" class="btn btn-ghost">
                        <x-tardis::icon name="arrow-left" class="w-4 h-4" />
                        {{ __('tardis::builder.back') }}
                    </button>
                @endif
            </div>

            <div class="flex items-center gap-2">
                @if ($step < 4)
                    <span class="text-xs text-base-content/50 hidden sm:inline">
                        {{ __('tardis::builder.step_of', ['step' => $step, 'total' => 4]) }}
                    </span>
                @endif

                @if ($step === 1)
                    <button wire:click="detectFields" class="btn btn-primary" {{ empty($model) ? 'disabled' : '' }}>
                        {{ __('tardis::builder.next_detect_fields') }}
                        <x-tardis::icon name="arrow-right" class="w-4 h-4" />
                    </button>
                @elseif ($step === 2)
                    <button wire:click="goToStep(3)" class="btn btn-primary" {{ empty($fieldConfig) ? 'disabled' : '' }}>
                        {{ __('tardis::builder.next_configure') }}
                        <x-tardis::icon name="arrow-right" class="w-4 h-4" />
                    </button>
                @elseif ($step === 3)
                    <button wire:click="goToStep(4)" class="btn btn-primary">
                        {{ __('tardis::builder.next_review') }}
                        <x-tardis::icon name="arrow-right" class="w-4 h-4" />
                    </button>
                @else
                    <button wire:click="save" class="btn btn-primary btn-wide">
                        <x-tardis::icon name="check-circle" class="w-4 h-4" />
                        {{ $editMode ? __('tardis::builder.update_bread') : __('tardis::builder.save_bread') }}
                    </button>
                @endif
            </div>
        </div>
    </div>

    <!-- Icon Picker Modal -->
    @if ($showIconPicker)
        <div wire:key="icon-picker">
            <dialog class="modal modal-open" @click.self="$set('showIconPicker', false)">
                <div class="modal-box w-full max-w-2xl">
                    <h3 class="font-bold text-lg mb-4">{{ __('tardis::builder.select_icon') }}</h3>

                    <label class="input flex items-center gap-2 w-full mb-4">
                        <x-tardis::icon name="magnifying-glass" class="w-4 h-4 text-base-content/60" />
                        <input type="text" wire:model.live.debounce.300ms="iconSearch" class="grow" placeholder="{{ __('tardis::builder.search_icons') }}" autofocus />
                    </label>

                    @php
                        $query = mb_strtolower(trim($iconSearch));
                        $visibleIcons = $query === ''
                            ? $this->iconOptions
                            : array_values(array_filter(
                                $this->iconOptions,
                                fn ($name) => str_contains($name, $query)
                            ));
                    @endphp

                    <div class="grid grid-cols-4 sm:grid-cols-6 md:grid-cols-8 gap-2 max-h-96 overflow-y-auto">
                        @forelse ($visibleIcons as $iconName)
                            <button type="button"
                                    wire:key="icon-{{ $iconName }}"
                                    wire:click="selectIcon('{{ $iconName }}')"
                                    class="btn btn-ghost btn-sm flex flex-col items-center gap-1 h-auto py-2 {{ $icon === $iconName ? 'btn-active' : '' }}"
                                    title="{{ $iconName }}">
                                <x-tardis::icon :name="$iconName" class="w-6 h-6" />
                                <span class="text-[10px] truncate w-full text-center">{{ $iconName }}</span>
                            </button>
                        @empty
                            <p class="col-span-full text-center py-8 text-base-content/50">{{ __('tardis::builder.no_icons_match', ['query' => $iconSearch]) }}</p>
                        @endforelse
                    </div>

                    <div class="modal-action">
                        <button type="button" wire:click="$set('showIconPicker', false)" class="btn btn-ghost">{{ __('tardis::builder.close') }}</button>
                    </div>
                </div>
                <button type="button" class="modal-backdrop" wire:click="$set('showIconPicker', false)">{{ __('tardis::builder.close_2') }}</button>
            </dialog>
        </div>
    @endif
</div>
