<?php

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Tardis\Bread\BreadDefinition;
use Tardis\Bread\ModelReflector;
use Tardis\Bread\Repositories\JsonBreadRepository;

new #[Title('BREAD Builder')] #[Layout('tardis::layouts.admin')] class extends Component
{
    public int $step = 1;

    public string $slug = '';

    public string $model = '';

    public string $name = '';

    public string $namePlural = '';

    public ?string $icon = null;

    public ?string $description = null;

    public array $detectedFields = [];

    public array $detectedRelationships = [];

    public array $fieldConfig = [];

    public array $relationshipConfig = [];

    public string $searchKey = '';

    public ?string $orderColumn = null;

    public string $orderDirection = 'asc';

    public ?string $activeTab = 'fields';

    public array $browseColumns = [];

    public array $editTabs = [];

    public bool $editMode = false;

    public ?string $existingSlug = null;

    public bool $showIconPicker = false;

    public string $iconSearch = '';

    public function mount(?string $slug = null): void
    {
        if ($slug) {
            $repo = app(JsonBreadRepository::class);
            $bread = $repo->find($slug);

            if ($bread) {
                $this->editMode = true;
                $this->existingSlug = $slug;
                $this->slug = $bread->slug;
                $this->model = $bread->model;
                $this->name = $bread->name;
                $this->namePlural = $bread->namePlural;
                $this->icon = $bread->icon;
                $this->description = $bread->description;
                $this->fieldConfig = $bread->fields;
                $this->searchKey = $bread->searchKey;
                $this->orderColumn = $bread->orderColumn;
                $this->orderDirection = $bread->orderDirection;
                $this->step = 3;
            }
        }
    }

    public function detectFields(): void
    {
        $this->validate([
            'model' => 'required|string',
        ]);

        if (! class_exists($this->model)) {
            session()->flash('error', 'Model class not found.');
            return;
        }

        $this->detectedFields = ModelReflector::getFields($this->model);
        $this->fieldConfig = $this->detectedFields;
        $this->detectedRelationships = ModelReflector::getRelationships(new $this->model);
        $this->relationshipConfig = $this->detectedRelationships;
        $this->name = class_basename($this->model);
        $this->namePlural = Str::headline(Str::plural($this->model));

        $this->step = 2;
    }

    public function goToStep(int $step): void
    {
        $this->step = $step;
    }

    public function save(): void
    {
        $this->validate([
            'slug' => 'required|regex:/^[a-z0-9-]+$/',
            'name' => 'required|string|max:255',
        ]);

        $bread = BreadDefinition::fromArray([
            'slug' => $this->slug,
            'model' => $this->model,
            'name' => $this->name,
            'name_plural' => $this->namePlural,
            'fields' => $this->fieldConfig,
            'icon' => $this->icon,
            'description' => $this->description,
            'search_key' => $this->searchKey ?: null,
            'order_column' => $this->orderColumn,
            'order_direction' => $this->orderDirection,
        ]);

        $repo = app(JsonBreadRepository::class);
        $repo->save($bread);

        session()->flash('message', 'BREAD definition saved successfully.');
        $this->redirect(route('tardis.bread.manage'));
    }

    public function getModelOptions(): array
    {
        $path = app_path('Models');
        $models = [];

        if (is_dir($path)) {
            foreach (glob($path.'/*.php') as $file) {
                $name = pathinfo($file, PATHINFO_FILENAME);
                $class = "App\\Models\\{$name}";
                if (class_exists($class)) {
                    $models[$class] = $name;
                }
            }
        }

        return $models;
    }

    public function addEditTab(): void
    {
        $this->editTabs[] = [
            'name' => 'Tab '.(count($this->editTabs) + 1),
            'fields' => [],
        ];
    }

    public function removeEditTab(int $index): void
    {
        unset($this->editTabs[$index]);
        $this->editTabs = array_values($this->editTabs);
    }
}; ?>

<div>
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold">{{ $editMode ? 'Edit BREAD' : 'Create BREAD' }}</h1>
            <p class="text-base-content/60 mt-1">{{ $editMode ? 'Modify your BREAD definition' : 'Define a new Browse/Read/Edit/Add/Delete resource' }}</p>
        </div>
    </div>

    @if (session('error'))
        <div class="alert alert-error mb-4 shadow-sm">
            <span>{{ session('error') }}</span>
        </div>
    @endif

    <!-- Steps -->
    <div class="steps steps-horizontal mb-6">
        <div class="step {{ $step >= 1 ? 'step-primary' : '' }}">Model</div>
        <div class="step {{ $step >= 2 ? 'step-primary' : '' }}">Fields</div>
        <div class="step {{ $step >= 3 ? 'step-primary' : '' }}">Configure</div>
        <div class="step {{ $step >= 4 ? 'step-primary' : '' }}">Save</div>
    </div>

    <!-- Step 1: Select Model -->
    @if ($step === 1)
        <div class="card bg-base-100 shadow-sm">
            <div class="card-body">
                <h2 class="card-title">Step 1: Select Model</h2>
                <p class="text-base-content/60">Choose an Eloquent model to create a BREAD for.</p>

                <div class="form-control mt-4">
                    <label class="label">
                        <span class="label-text">Model Class</span>
                    </label>
                    <select wire:model="model" class="select select-bordered w-full">
                        <option value="">Select a model...</option>
                        @foreach ($this->getModelOptions() as $class => $name)
                            <option value="{{ $class }}">{{ $name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="card-actions justify-end mt-6">
                    <button wire:click="detectFields" class="btn btn-primary" {{ empty($model) ? 'disabled' : '' }}>
                        Next: Detect Fields
                    </button>
                </div>
            </div>
        </div>
    @endif

    <!-- Step 2: Review Fields & Relationships -->
    @if ($step === 2)
        <div class="card bg-base-100 shadow-sm">
            <div class="card-body">
                <div class="tabs tabs-box mb-4">
                    <button wire:click="$set('activeTab', 'fields')" role="tab" class="tab {{ ($activeTab ?? 'fields') === 'fields' ? 'tab-active' : '' }}">Fields</button>
                    <button wire:click="$set('activeTab', 'relationships')" role="tab" class="tab {{ ($activeTab ?? '') === 'relationships' ? 'tab-active' : '' }}">Relationships</button>
                </div>

                @if (($activeTab ?? 'fields') === 'fields')
                    <h2 class="card-title">Detected Fields</h2>
                    <p class="text-base-content/60">Review and configure the detected fields.</p>

                    <div class="overflow-x-auto mt-4">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Field</th>
                                    <th>Type</th>
                                    <th>Browse</th>
                                    <th>Read</th>
                                    <th>Edit</th>
                                    <th>Add</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($fieldConfig as $key => $field)
                                    <tr>
                                        <td class="font-medium">{{ $field['label'] }}</td>
                                        <td>
                                            <select wire:model="fieldConfig.{{ $key }}.type" class="select select-bordered select-xs">
                                                @foreach (\Tardis\Classes\Setting::availableTypes() as $type => $label)
                                                    <option value="{{ $type }}">{{ $label }}</option>
                                                @endforeach
                                            </select>
                                        </td>
                                        <td><input type="checkbox" wire:model="fieldConfig.{{ $key }}.browse" class="checkbox checkbox-sm" /></td>
                                        <td><input type="checkbox" wire:model="fieldConfig.{{ $key }}.read" class="checkbox checkbox-sm" /></td>
                                        <td><input type="checkbox" wire:model="fieldConfig.{{ $key }}.edit" class="checkbox checkbox-sm" /></td>
                                        <td><input type="checkbox" wire:model="fieldConfig.{{ $key }}.add" class="checkbox checkbox-sm" /></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <h2 class="card-title">Detected Relationships</h2>
                    <p class="text-base-content/60">Configure how relationships are displayed in BREAD.</p>

                    @if (empty($relationshipConfig))
                        <div class="text-center py-8 opacity-50">
                            <p>No relationships detected in this model.</p>
                        </div>
                    @else
                        <div class="overflow-x-auto mt-4">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Relation</th>
                                        <th>Type</th>
                                        <th>Related Model</th>
                                        <th>Display Type</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($relationshipConfig as $name => $rel)
                                        <tr>
                                            <td class="font-medium">{{ $name }}</td>
                                            <td><span class="badge badge-ghost badge-sm">{{ $rel['type'] }}</span></td>
                                            <td>{{ class_basename($rel['model']) }}</td>
                                            <td>
                                                <select wire:model="relationshipConfig.{{ $name }}.display_type" class="select select-bordered select-xs">
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

                <div class="card-actions justify-end mt-6">
                    <button wire:click="goToStep(1)" class="btn btn-ghost">Back</button>
                    <button wire:click="goToStep(3)" class="btn btn-primary">Next: Configure</button>
                </div>
            </div>
        </div>
    @endif

    <!-- Step 3: Configure -->
    @if ($step === 3)
        <div class="card bg-base-100 shadow-sm">
            <div class="card-body">
                <div class="tabs tabs-box mb-4">
                    <button wire:click="$set('activeTab', 'general')" role="tab" class="tab {{ ($activeTab ?? 'general') === 'general' ? 'tab-active' : '' }}">General</button>
                    <button wire:click="$set('activeTab', 'browse-layout')" role="tab" class="tab {{ ($activeTab ?? '') === 'browse-layout' ? 'tab-active' : '' }}">Browse Layout</button>
                    <button wire:click="$set('activeTab', 'edit-layout')" role="tab" class="tab {{ ($activeTab ?? '') === 'edit-layout' ? 'tab-active' : '' }}">Edit Layout</button>
                </div>

                @if (($activeTab ?? 'general') === 'general')
                    <h2 class="card-title">General Settings</h2>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                    <div class="form-control">
                        <label class="label"><span class="label-text">Slug (URL) *</span></label>
                        <input type="text" wire:model="slug" class="input input-bordered" placeholder="e.g., posts" />
                    </div>

                    <div class="form-control">
                        <label class="label"><span class="label-text">Name *</span></label>
                        <input type="text" wire:model="name" class="input input-bordered" placeholder="e.g., Post" />
                    </div>

                    <div class="form-control">
                        <label class="label"><span class="label-text">Name (Plural)</span></label>
                        <input type="text" wire:model="namePlural" class="input input-bordered" placeholder="e.g., Posts" />
                    </div>

                    <div class="form-control">
                        <label class="label"><span class="label-text">Icon</span></label>
                        <div class="flex gap-2">
                            <input type="text" wire:model="icon" class="input input-bordered flex-1" placeholder="e.g., document-text" readonly />
                            <button type="button" wire:click="$set('showIconPicker', true)" class="btn btn-outline">
                                <x-tardis::icon name="cog-6-tooth" class="w-4 h-4" />
                                Pick
                            </button>
                        </div>
                    </div>

                    <div class="form-control md:col-span-2">
                        <label class="label"><span class="label-text">Description</span></label>
                        <textarea wire:model="description" class="textarea textarea-bordered" rows="2"></textarea>
                    </div>

                    <div class="form-control">
                        <label class="label"><span class="label-text">Search Key</span></label>
                        <input type="text" wire:model="searchKey" class="input input-bordered" placeholder="Field for global search" />
                    </div>

                    <div class="form-control">
                        <label class="label"><span class="label-text">Order Column</span></label>
                        <input type="text" wire:model="orderColumn" class="input input-bordered" placeholder="e.g., created_at" />
                    </div>

                    <div class="form-control">
                        <label class="label"><span class="label-text">Order Direction</span></label>
                        <select wire:model="orderDirection" class="select select-bordered">
                            <option value="asc">Ascending</option>
                            <option value="desc">Descending</option>
                        </select>
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
                                    <th>Field</th>
                                    <th>Visible</th>
                                    <th>Sortable</th>
                                    <th>Searchable</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($fieldConfig as $key => $field)
                                    <tr>
                                        <td class="font-medium">{{ $field['label'] }}</td>
                                        <td><input type="checkbox" wire:model="browseColumns.{{ $key }}.visible" class="checkbox checkbox-sm" checked /></td>
                                        <td><input type="checkbox" wire:model="browseColumns.{{ $key }}.sortable" class="checkbox checkbox-sm" /></td>
                                        <td><input type="checkbox" wire:model="browseColumns.{{ $key }}.searchable" class="checkbox checkbox-sm" /></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
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
                            <div class="card bg-base-200 mb-3">
                                <div class="card-body p-4">
                                    <div class="flex items-center gap-2 mb-3">
                                        <input type="text" wire:model="editTabs.{{ $tabIndex }}.name" class="input input-bordered input-sm flex-1" placeholder="Tab name" />
                                        <button wire:click="removeEditTab({{ $tabIndex }})" class="btn btn-ghost btn-xs text-error">
                                            <x-tardis::icon name="x-mark" class="w-4 h-4" />
                                        </button>
                                    </div>
                                    <div class="flex flex-wrap gap-2">
                                        @foreach ($fieldConfig as $key => $field)
                                            <label class="flex items-center gap-1 cursor-pointer">
                                                <input type="checkbox" wire:model="editTabs.{{ $tabIndex }}.fields" value="{{ $key }}" class="checkbox checkbox-sm checkbox-primary" />
                                                <span class="text-xs">{{ $field['label'] }}</span>
                                            </label>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        @empty
                            <p class="text-sm opacity-50">No tabs configured. Fields will appear in a single form.</p>
                        @endforelse
                    </div>
                @endif
            </div>
        </div>
    @endif

    <!-- Icon Picker Modal -->
    @if ($showIconPicker)
        <dialog class="modal modal-open">
            <div class="modal-box w-full max-w-2xl">
                <h3 class="font-bold text-lg mb-4">Select Icon</h3>

                <input type="text" wire:model="iconSearch" class="input input-bordered w-full mb-4" placeholder="Search icons..." />

                <div class="grid grid-cols-6 sm:grid-cols-8 gap-2 max-h-96 overflow-y-auto">
                    @php
                        $icons = ['bars-3', 'sun', 'moon', 'table-cells', 'document-text', 'pencil-square', 'plus-circle', 'database', 'clock', 'photo', 'puzzle-piece', 'check-circle', 'x-circle', 'power', 'check', 'x-mark', 'plus', 'folder', 'cog-6-tooth', 'lock-closed', 'paper-clip', 'hashtag', 'chevron-up-down', 'calendar-days', 'toggle', 'text', 'user-group'];
                        $filteredIcons = $iconSearch ? array_filter($icons, fn ($i) => str_contains($i, strtolower($iconSearch))) : $icons;
                    @endphp

                    @foreach ($filteredIcons as $iconName)
                        <button type="button"
                                wire:dblclick="$set('icon', '{{ $iconName }}'); $set('showIconPicker', false)"
                                class="btn btn-ghost btn-sm flex flex-col items-center gap-1 h-auto py-2 {{ $icon === $iconName ? 'btn-active' : '' }}"
                                title="{{ $iconName }}">
                            <x-tardis::icon :name="$iconName" class="w-6 h-6" />
                            <span class="text-[10px] truncate w-full text-center">{{ $iconName }}</span>
                        </button>
                    @endforeach
                </div>

                <div class="modal-action">
                    <button wire:click="$set('showIconPicker', false)" class="btn btn-ghost">Close</button>
                </div>
            </div>
            <form method="dialog" class="modal-backdrop">
                <button wire:click="$set('showIconPicker', false)">close</button>
            </form>
        </dialog>
    @endif
</div>
