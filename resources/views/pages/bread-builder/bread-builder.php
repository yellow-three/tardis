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

    public bool $softDelete = false;

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
            'relationships' => $this->relationshipConfig,
            'icon' => $this->icon,
            'description' => $this->description,
            'search_key' => $this->searchKey ?: null,
            'order_column' => $this->orderColumn,
            'order_direction' => $this->orderDirection,
            'soft_delete' => $this->softDelete,
            'layout' => [
                'browse' => $this->browseColumns,
                'edit' => $this->editTabs,
            ],
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
};
