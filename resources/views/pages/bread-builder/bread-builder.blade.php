@php
    $fieldGroups = [
        'Text & Content' => ['text', 'textarea', 'markdown', 'code_editor', 'slug', 'password'],
        'Numbers' => ['number', 'slider'],
        'Choice' => ['select', 'radio', 'checkbox', 'toggle', 'tags'],
        'Date & Time' => ['date', 'datetime', 'time'],
        'File' => ['file'],
        'Relations' => ['belongs_to_many', 'has_many'],
    ];

    $slugBadges = [
        'available' => ['badge-success', 'Available'],
        'current' => ['badge-info', 'Current slug'],
        'taken' => ['badge-error', 'Already used'],
        'invalid' => ['badge-warning', 'Lowercase letters, numbers and dashes only'],
        'empty' => ['badge-ghost', 'Not set yet'],
    ];
@endphp

<div>
    <x-tardis::page-header
        :title="$editMode ? 'Edit BREAD' : 'Create BREAD'"
        :description="$editMode ? 'Modify your BREAD definition' : 'Define a new Browse/Read/Edit/Add/Delete resource'"
    >
        <x-slot:action>
            <label class="label cursor-pointer justify-start gap-2">
                <input type="checkbox" wire:model.live="focusMode" class="toggle toggle-primary toggle-sm" />
                <span class="text-base-content">Focus mode</span>
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
                <p class="font-semibold">Please fix the following before saving:</p>
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
            <span class="text-sm">Focus mode hides read-only hints so you can move faster through the wizard.</span>
        </div>
    @endif

    <!-- Steps -->
    <ul class="steps steps-horizontal w-full mb-6">
        @foreach ([1 => 'Model', 2 => 'Fields', 3 => 'Configure', 4 => 'Review'] as $index => $label)
            <li wire:click="goToStep({{ $index }})" class="step cursor-pointer {{ $step >= $index ? 'step-primary' : '' }}">
                {{ $label }}
            </li>
        @endforeach
    </ul>

    <!-- Step 1: Select Model -->
    @if ($step === 1)
        <div class="card bg-base-100 border border-base-300">
            <div class="card-body">
                <h2 class="card-title">Step 1: Select Model</h2>
                <p class="text-base-content/60">Choose an Eloquent model to create a BREAD for.</p>

                <div class="flex flex-col gap-2 mt-4">
                    <label class="label">
                        <span class="text-base-content">Model Class</span>
                    </label>
                    <select wire:model.change.live="model" class="select w-full">
                        <option value="">Select a model...</option>
                        @foreach ($this->getModelOptions() as $class => $name)
                            <option value="{{ $class }}">{{ $name }}</option>
                        @endforeach
                    </select>
                    @if (! $focusMode)
                        <label class="label">
                            <span class="text-base-content/50">
                                Fields are read from the model's <code>$fillable</code> array.
                            </span>
                        </label>
                    @endif
                </div>

                @if ($model !== '' && $modelTable !== '')
                    <div class="stats stats-vertical sm:stats-horizontal mt-4 bg-base-200">
                        <div class="stat py-3">
                            <div class="stat-title text-xs">Table</div>
                            <div class="stat-value text-lg">{{ $modelTable }}</div>
                        </div>
                        <div class="stat py-3">
                            <div class="stat-title text-xs">Timestamps</div>
                            <div class="stat-value text-lg">{{ $modelHasTimestamps ? 'Yes' : 'No' }}</div>
                        </div>
                        <div class="stat py-3">
                            <div class="stat-title text-xs">Soft Deletes</div>
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
                        Fields
                        <span class="badge badge-sm badge-ghost ml-1">{{ count($fieldConfig) }}</span>
                    </button>
                    <button wire:click="$set('activeTab', 'relationships')" role="tab" class="tab {{ ($activeTab ?? '') === 'relationships' ? 'tab-active' : '' }}">
                        Relationships
                        <span class="badge badge-sm badge-ghost ml-1">{{ count($relationshipConfig) }}</span>
                    </button>
                </div>

                @if (($activeTab ?? 'fields') === 'fields')
                    <div class="flex flex-wrap items-end justify-between gap-3">
                        <div>
                            <h2 class="card-title">Detected Fields</h2>
                            <p class="text-base-content/60">Review and configure the detected fields.</p>
                        </div>

                        <label class="input input-sm flex items-center gap-2 w-full sm:w-64">
                            <x-tardis::icon name="magnifying-glass" class="w-4 h-4 text-base-content/60" />
                            <input type="text" wire:model.live.debounce.300ms="fieldSearch" class="grow" placeholder="Filter fields..." />
                        </label>
                    </div>

                    <div class="flex flex-wrap items-center gap-2 mt-4 p-3 rounded-box bg-base-200">
                        <span class="text-xs font-semibold uppercase tracking-wide text-base-content/60">Set all</span>
                        @foreach (['browse' => 'B', 'read' => 'R', 'edit' => 'E', 'add' => 'A'] as $flag => $letter)
                            <button wire:click="toggleAllFields('{{ $flag }}', true)" class="btn btn-xs btn-outline">
                                {{ $letter }} on
                            </button>
                            <button wire:click="toggleAllFields('{{ $flag }}', false)" class="btn btn-xs btn-ghost">
                                {{ $letter }} off
                            </button>
                        @endforeach
                    </div>

                    <div class="overflow-x-auto mt-4">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th class="w-10" aria-hidden="true"></th>
                                    <th scope="col">Field</th>
                                    <th scope="col">Type</th>
                                    <th class="text-center" scope="col">Browse</th>
                                    <th class="text-center" scope="col">Read</th>
                                    <th class="text-center" scope="col">Edit</th>
                                    <th class="text-center" scope="col">Add</th>
                                    @unless ($focusMode)
                                        <th scope="col">Validation</th>
                                    @endunless
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($this->visibleFieldKeys as $position => $key)
                                    @php $field = $fieldConfig[$key]; @endphp
                                    <tr wire:key="field-{{ $key }}">
                                        <td>
                                            <div class="join join-vertical">
                                                <button wire:click="moveField('{{ $key }}', -1)" class="btn join-item btn-ghost btn-xs px-1" title="Move up">
                                                    <x-tardis::icon name="chevron-up" class="w-3 h-3" />
                                                </button>
                                                <button wire:click="moveField('{{ $key }}', 1)" class="btn join-item btn-ghost btn-xs px-1" title="Move down">
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
                                                No fields match "{{ $fieldSearch }}".
                                            @else
                                                No fields detected. Add entries to the model's <code>$fillable</code> array.
                                            @endif
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                @else
                    <h2 class="card-title">Detected Relationships</h2>
                    <p class="text-base-content/60">Configure how relationships are displayed in BREAD.</p>

                    @if (empty($relationshipConfig))
                        <div class="text-center py-8 text-base-content/50">
                            <p>No relationships detected in this model.</p>
                        </div>
                    @else
                        <div class="overflow-x-auto mt-4">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th scope="col">Relation</th>
                                        <th scope="col">Type</th>
                                        <th scope="col">Related Model</th>
                                        <th scope="col">Display Type</th>
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
                                                    <option value="select">Select</option>
                                                    <option value="checkbox">Checkbox</option>
                                                    <option value="table">Table</option>
                                                    <option value="hidden">Hidden</option>
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
                    <button wire:click="$set('activeTab', 'general')" role="tab" class="tab {{ ($activeTab ?? 'general') === 'general' ? 'tab-active' : '' }}">General</button>
                    <button wire:click="$set('activeTab', 'browse-layout')" role="tab" class="tab {{ ($activeTab ?? '') === 'browse-layout' ? 'tab-active' : '' }}">Browse Layout</button>
                    <button wire:click="$set('activeTab', 'read-layout')" role="tab" class="tab {{ ($activeTab ?? '') === 'read-layout' ? 'tab-active' : '' }}">Read Layout</button>
                    <button wire:click="$set('activeTab', 'edit-layout')" role="tab" class="tab {{ ($activeTab ?? '') === 'edit-layout' ? 'tab-active' : '' }}">Edit Layout</button>
                </div>

                @if (($activeTab ?? 'general') === 'general')
                    <h2 class="card-title">General Settings</h2>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                        <div class="flex flex-col gap-2">
                            <label class="label">
                                <span class="text-base-content">Slug (URL) *</span>
                                @php [$slugClass, $slugLabel] = $slugBadges[$this->slugStatus]; @endphp
                                <span class="badge badge-sm {{ $slugClass }}">{{ $slugLabel }}</span>
                            </label>
                            <input type="text" wire:model="slug" class="input font-mono" placeholder="e.g., posts" />
                            @unless ($focusMode)
                                <label class="label">
                                    <span class="text-base-content/50">
                                        Generated from the plural name until you type one by hand.
                                    </span>
                                </label>
                            @endunless
                        </div>

                        <div class="flex flex-col gap-2">
                            <label class="label"><span class="text-base-content">Name *</span></label>
                            <input type="text" wire:model="name" class="input" placeholder="e.g., Post" />
                        </div>

                        <div class="flex flex-col gap-2">
                            <label class="label"><span class="text-base-content">Name (Plural)</span></label>
                            <input type="text" wire:model="namePlural" class="input" placeholder="e.g., Posts" />
                        </div>

                        <div class="flex flex-col gap-2">
                            <label class="label"><span class="text-base-content">Icon</span></label>
                            <div class="flex gap-2">
                                <div class="flex-1 flex items-center gap-2 input">
                                    <x-tardis::icon :name="$icon ?: 'swatch'" class="w-4 h-4 text-base-content/60" />
                                    <span class="truncate font-mono text-sm">{{ $icon ?: 'none' }}</span>
                                </div>
                                <button type="button" wire:click="$set('showIconPicker', true)" class="btn btn-outline">
                                    <x-tardis::icon name="swatch" class="w-4 h-4" />
                                    Pick
                                </button>
                                @if ($icon)
                                    <button type="button" wire:click="$set('icon', null)" class="btn btn-ghost" title="Clear icon">
                                        <x-tardis::icon name="x-mark" class="w-4 h-4" />
                                    </button>
                                @endif
                            </div>
                        </div>

                        <div class="flex flex-col gap-2 md:col-span-2">
                            <label class="label"><span class="text-base-content">Description</span></label>
                            <textarea wire:model="description" class="textarea" rows="2"></textarea>
                        </div>

                        <div class="flex flex-col gap-2">
                            <label class="label"><span class="text-base-content">Search Key</span></label>
                            <input type="text" wire:model="searchKey" class="input" placeholder="Field for global search" />
                        </div>

                        <div class="flex flex-col gap-2">
                            <label class="label"><span class="text-base-content">Order Column</span></label>
                            <input type="text" wire:model="orderColumn" class="input" placeholder="e.g., created_at" />
                        </div>

                        <div class="flex flex-col gap-2">
                            <label class="label"><span class="text-base-content">Order Direction</span></label>
                            <select wire:model="orderDirection" class="select">
                                <option value="asc">Ascending</option>
                                <option value="desc">Descending</option>
                            </select>
                        </div>

                        <div class="flex flex-col gap-2">
                            <label class="label cursor-pointer justify-start gap-3">
                                <input type="checkbox" wire:model.live="softDelete" class="toggle toggle-primary" />
                                <span class="text-base-content">Enable Soft Delete</span>
                            </label>
                            @if ($modelHasSoftDeletes)
                                <label class="label">
                                    <span class="text-warning">This model already uses SoftDeletes.</span>
                                </label>
                            @else
                                <label class="label">
                                    <span class="text-base-content/50">Allow restoring deleted items</span>
                                </label>
                            @endif
                        </div>
                    </div>
                @endif

                @if (($activeTab ?? '') === 'browse-layout')
                    <h2 class="card-title">Browse Layout</h2>
                    <p class="text-base-content/60">Configure which columns appear in the browse table.</p>

                    <div class="overflow-x-auto mt-4">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th scope="col">Field</th>
                                    <th class="text-center" scope="col">Visible</th>
                                    <th class="text-center" scope="col">Sortable</th>
                                    <th class="text-center" scope="col">Searchable</th>
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
                                        <td colspan="4" class="text-center py-8 text-base-content/50">No fields to lay out yet.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                @endif

                @if (($activeTab ?? '') === 'read-layout')
                    <h2 class="card-title">Read Layout</h2>
                    <p class="text-base-content/60">Choose the order fields appear in on the detail view.</p>

                    <div class="mt-4 space-y-2">
                        @forelse ($readLayout as $position => $key)
                            <div wire:key="read-{{ $key }}" class="flex items-center gap-3 p-2 rounded-box bg-base-200">
                                <span class="badge badge-neutral badge-sm">{{ $position + 1 }}</span>
                                <span class="font-medium flex-1">{{ $fieldConfig[$key]['label'] ?? $key }}</span>
                                <button wire:click="moveReadField('{{ $key }}', -1)" class="btn btn-ghost btn-xs px-1" title="Move up">
                                    <x-tardis::icon name="chevron-up" class="w-3 h-3" />
                                </button>
                                <button wire:click="moveReadField('{{ $key }}', 1)" class="btn btn-ghost btn-xs px-1" title="Move down">
                                    <x-tardis::icon name="chevron-down" class="w-3 h-3" />
                                </button>
                                <button wire:click="toggleReadField('{{ $key }}')" class="btn btn-ghost btn-xs text-error" title="Remove">
                                    <x-tardis::icon name="x-mark" class="w-3 h-3" />
                                </button>
                            </div>
                        @empty
                            <p class="text-sm text-base-content/50 py-4 text-center">No fields in the read layout.</p>
                        @endforelse
                    </div>

                    @if (count($readLayout) < count($this->orderedFieldKeys))
                        <div class="mt-4">
                            <p class="text-xs uppercase tracking-wide text-base-content/60 mb-2">Add fields</p>
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

                @if (($activeTab ?? '') === 'edit-layout')
                    <h2 class="card-title">Edit Layout</h2>
                    <p class="text-base-content/60">Configure tabs and field grouping for the edit form.</p>

                    <div class="mt-4">
                        <div class="flex items-center gap-2 mb-4">
                            <button wire:click="addEditTab" class="btn btn-outline btn-sm gap-1">
                                <x-tardis::icon name="plus" class="w-4 h-4" />
                                Add Tab
                            </button>
                        </div>

                        @forelse ($editTabs as $tabIndex => $tab)
                            <div wire:key="tab-{{ $tabIndex }}" class="card bg-base-200 mb-3 border border-base-300">
                                <div class="card-body p-4">
                                    <div class="flex items-center gap-2 mb-3">
                                        <input type="text" wire:model="editTabs.{{ $tabIndex }}.name" class="input input-sm flex-1" placeholder="Tab name" />
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
                            <p class="text-sm text-base-content/50">No tabs configured. Fields will appear in a single form.</p>
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
                        <h2 class="card-title text-base">Before you save</h2>
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
                    <h2 class="card-title">Summary</h2>

                    <div class="overflow-x-auto">
                        <table class="table">
                            <tbody>
                                <tr>
                                    <th class="w-48" scope="row">Slug</th>
                                    <td class="font-mono">{{ $this->reviewSummary['slug'] ?: '—' }}</td>
                                </tr>
                                <tr>
                                    <th scope="row">Model</th>
                                    <td>
                                        {{ class_basename($this->reviewSummary['model'] ?: '') ?: '—' }}
                                        <span class="badge badge-ghost badge-sm ml-2">{{ $this->reviewSummary['table'] ?: 'no table' }}</span>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">Names</th>
                                    <td>{{ $this->reviewSummary['name'] ?: '—' }} <span class="text-base-content/50">/</span> {{ $this->reviewSummary['name_plural'] ?: '—' }}</td>
                                </tr>
                                <tr>
                                    <th scope="row">Icon</th>
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
                                    <th scope="row">Fields</th>
                                    <td>
                                        <span class="badge badge-sm">{{ $this->reviewSummary['total_fields'] }} total</span>
                                        <span class="badge badge-sm badge-ghost ml-1">{{ $this->reviewSummary['browse_fields'] ? count($this->reviewSummary['browse_fields']) : 0 }} in browse</span>
                                        <span class="badge badge-sm badge-ghost ml-1">{{ count($this->reviewSummary['read_fields']) }} in read</span>
                                        <span class="badge badge-sm badge-ghost ml-1">{{ $this->reviewSummary['add_fields'] }} addable</span>
                                        <span class="badge badge-sm badge-ghost ml-1">{{ $this->reviewSummary['edit_fields'] }} editable</span>
                                    </td>
                                </tr>
                                @if ($this->reviewSummary['browse_fields'])
                                    <tr>
                                        <th scope="row">Browse columns</th>
                                        <td class="flex flex-wrap gap-1">
                                            @foreach ($this->reviewSummary['browse_fields'] as $key)
                                                <span class="badge badge-outline badge-sm">{{ $fieldConfig[$key]['label'] ?? $key }}</span>
                                            @endforeach
                                        </td>
                                    </tr>
                                @endif
                                <tr>
                                    <th scope="row">Relationships</th>
                                    <td>{{ $this->reviewSummary['relationships'] }}</td>
                                </tr>
                                <tr>
                                    <th scope="row">Search key</th>
                                    <td class="font-mono">{{ $this->reviewSummary['search_key'] ?: '—' }}</td>
                                </tr>
                                <tr>
                                    <th scope="row">Ordering</th>
                                    <td class="font-mono">
                                        {{ $this->reviewSummary['order_column'] ?: 'none' }}
                                        @if ($this->reviewSummary['order_column'])
                                            <span class="badge badge-ghost badge-sm ml-1">{{ $this->reviewSummary['order_direction'] }}</span>
                                        @endif
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">Soft delete</th>
                                    <td>
                                        @if ($this->reviewSummary['soft_delete'])
                                            <span class="badge badge-success badge-sm">Enabled</span>
                                        @else
                                            <span class="badge badge-ghost badge-sm">Disabled</span>
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
                        Back
                    </button>
                @endif
            </div>

            <div class="flex items-center gap-2">
                @if ($step < 4)
                    <span class="text-xs text-base-content/50 hidden sm:inline">
                        Step {{ $step }} of 4
                    </span>
                @endif

                @if ($step === 1)
                    <button wire:click="detectFields" class="btn btn-primary" {{ empty($model) ? 'disabled' : '' }}>
                        Next: Detect Fields
                        <x-tardis::icon name="arrow-right" class="w-4 h-4" />
                    </button>
                @elseif ($step === 2)
                    <button wire:click="goToStep(3)" class="btn btn-primary" {{ empty($fieldConfig) ? 'disabled' : '' }}>
                        Next: Configure
                        <x-tardis::icon name="arrow-right" class="w-4 h-4" />
                    </button>
                @elseif ($step === 3)
                    <button wire:click="goToStep(4)" class="btn btn-primary">
                        Next: Review
                        <x-tardis::icon name="arrow-right" class="w-4 h-4" />
                    </button>
                @else
                    <button wire:click="save" class="btn btn-primary btn-wide">
                        <x-tardis::icon name="check-circle" class="w-4 h-4" />
                        {{ $editMode ? 'Update BREAD' : 'Save BREAD' }}
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
                    <h3 class="font-bold text-lg mb-4">Select Icon</h3>

                    <label class="input flex items-center gap-2 w-full mb-4">
                        <x-tardis::icon name="magnifying-glass" class="w-4 h-4 text-base-content/60" />
                        <input type="text" wire:model.live.debounce.300ms="iconSearch" class="grow" placeholder="Search icons..." autofocus />
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
                            <p class="col-span-full text-center py-8 text-base-content/50">No icons match "{{ $iconSearch }}".</p>
                        @endforelse
                    </div>

                    <div class="modal-action">
                        <button type="button" wire:click="$set('showIconPicker', false)" class="btn btn-ghost">Close</button>
                    </div>
                </div>
                <button type="button" class="modal-backdrop" wire:click="$set('showIconPicker', false)">close</button>
            </dialog>
        </div>
    @endif
</div>
