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

    public array $fieldConfig = [];

    public string $searchKey = '';

    public ?string $orderColumn = null;

    public string $orderDirection = 'asc';

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

    <!-- Step 2: Review Fields -->
    @if ($step === 2)
        <div class="card bg-base-100 shadow-sm">
            <div class="card-body">
                <h2 class="card-title">Step 2: Detected Fields</h2>
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
                <h2 class="card-title">Step 3: Configure BREAD</h2>

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

                <div class="card-actions justify-end mt-6">
                    <button wire:click="goToStep(2)" class="btn btn-ghost">Back</button>
                    <button wire:click="save" class="btn btn-primary">Save BREAD</button>
                </div>
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
