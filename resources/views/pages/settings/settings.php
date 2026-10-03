<?php

use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Tardis\Classes\Setting;
use Tardis\Facades\Tardis;

new #[Title('Settings')] #[Layout('tardis::layouts.admin')] class extends Component
{
    public array $groups = [];

    public array $values = [];

    public ?string $activeGroup = null;

    public bool $saved = false;

    public string $search = '';

    public bool $showAddModal = false;

    public bool $showAddGroupModal = false;

    public string $newGroupName = '';

    public bool $showDeleteModal = false;

    public ?string $deleteKey = null;

    public string $newKey = '';

    public string $newGroup = '';

    public string $newType = 'text';

    public string $newName = '';

    public string $newInfo = '';

    public mixed $newDefaultValue = null;

    public string $newValidation = '';

    public ?string $exportJson = null;

    public string $importJson = '';

    public bool $showImportModal = false;

    public bool $showExportModal = false;

    public bool $showJsonPanel = false;

    public function mount(): void
    {
        $this->loadSettings();
    }

    public function loadSettings(): void
    {
        $allSettings = Tardis::settings()->all();
        $this->groups = [];
        $this->values = [];

        foreach ($allSettings as $setting) {
            $group = $setting->group ?? '_ungrouped';

            if (! isset($this->groups[$group])) {
                $this->groups[$group] = [
                    'label' => $group === '_ungrouped' ? 'General' : $group,
                    'icon' => $this->groupIcon($group),
                    'settings' => [],
                ];
            }

            $this->groups[$group]['settings'][] = [
                'uuid' => $setting->uuid ?? $setting->key,
                'key' => $setting->key,
                'type' => $setting->type,
                'name' => $setting->displayName(),
                'value' => $setting->value,
                'info' => $setting->info,
                'options' => $setting->options,
                'translatable' => $setting->translatable,
                'canBeTranslated' => $setting->canBeTranslated,
                'validation' => $setting->validation,
                'fullKey' => $setting->getFullKey(),
            ];

            if (! isset($this->values[$group])) {
                $this->values[$group] = [];
            }
            $this->values[$group][$setting->key] = $setting->displayValue();
        }

        $keys = array_keys($this->groups);
        $this->activeGroup ??= $keys[0] ?? null;
    }

    public function setActiveGroup(string $group): void
    {
        $this->activeGroup = $group;
        $this->saved = false;
    }

    public function save(): void
    {
        $data = [];
        $errors = [];

        $settings = Tardis::settings()->all();
        foreach ($settings as $setting) {
            $group = $setting->group ?? '_ungrouped';
            $key = $setting->key;

            if (isset($this->values[$group][$key])) {
                $value = $this->values[$group][$key];

                if (empty($setting->key)) {
                    $errors[$setting->getFullKey()] = 'Key is required.';

                    continue;
                }

                if (! empty($setting->validation)) {
                    $validator = Validator::make(
                        ['value' => $value],
                        ['value' => $setting->validation]
                    );

                    if ($validator->fails()) {
                        $errors[$setting->getFullKey()] = $validator->errors()->first('value');

                        continue;
                    }
                }

                $data[$setting->getFullKey()] = $value;
            }
        }

        if (! empty($errors)) {
            $this->dispatch('settings-errors', errors: $errors);

            return;
        }

        Tardis::settings()->update($data);
        $this->dispatch('settings-saved');
        $this->saved = false;
    }

    public function createSetting(): void
    {
        $this->validate([
            'newKey' => 'required|regex:/^[a-z0-9._-]+$/',
            'newName' => 'required|string|max:255',
            'newType' => 'required|in:'.implode(',', array_keys(Setting::availableTypes())),
        ], [
            'newKey.required' => 'Key is required.',
            'newKey.regex' => 'Key must contain only lowercase letters, numbers, dots, hyphens, and underscores.',
            'newName.required' => 'Label is required.',
            'newType.required' => 'Type is required.',
        ]);

        $validation = [];
        if ($this->newValidation !== '') {
            $validation = array_map('trim', explode('|', $this->newValidation));
        }

        Tardis::settings()->create([
            'key' => $this->newKey,
            'group' => $this->newGroup ?: null,
            'type' => $this->newType,
            'name' => $this->newName,
            'info' => $this->newInfo ?: null,
            'value' => $this->newDefaultValue,
            'validation' => $validation,
        ]);

        $this->resetNewSettingFields();
        $this->showAddModal = false;
        $this->loadSettings();
    }

    public function confirmDelete(string $key): void
    {
        $this->deleteKey = $key;
        $this->showDeleteModal = true;
    }

    public function deleteSetting(): void
    {
        if ($this->deleteKey) {
            Tardis::settings()->delete($this->deleteKey);
            $this->deleteKey = null;
            $this->showDeleteModal = false;
            $this->loadSettings();
        }
    }

    public function cancelDelete(): void
    {
        $this->deleteKey = null;
        $this->showDeleteModal = false;
    }

    public function cloneSetting(string $fullKey): void
    {
        Tardis::settings()->duplicate($fullKey);
        $this->loadSettings();
    }

    public function addGroup(): void
    {
        $this->validate([
            'newGroupName' => 'required|string|max:255',
        ]);

        $slug = Str::slug($this->newGroupName);

        if (! isset($this->groups[$slug])) {
            $this->groups[$slug] = [
                'label' => $this->newGroupName,
                'icon' => 'cog-6-tooth',
                'settings' => [],
            ];
            $this->values[$slug] = [];
        }

        $this->activeGroup = $slug;
        $this->newGroupName = '';
        $this->showAddGroupModal = false;
    }

    public function generateKey(string $uuid): void
    {
        foreach ($this->groups as $groupKey => $group) {
            foreach ($group['settings'] as $index => $setting) {
                if ($setting['uuid'] === $uuid) {
                    $this->values[$groupKey][$setting['key']] = Str::slug($setting['name']);
                    break;
                }
            }
        }
    }

    public function getFilteredGroups(): array
    {
        if ($this->search === '') {
            return $this->groups;
        }

        $filtered = [];
        foreach ($this->groups as $groupKey => $group) {
            $filteredSettings = array_filter($group['settings'], function ($setting) {
                return str_contains(strtolower($setting['key']), strtolower($this->search))
                    || str_contains(strtolower($setting['name']), strtolower($this->search));
            });

            if (! empty($filteredSettings)) {
                $filtered[$groupKey] = $group;
                $filtered[$groupKey]['settings'] = array_values($filteredSettings);
            }
        }

        return $filtered;
    }

    public function getJsonOutput(): string
    {
        $output = [];
        foreach ($this->groups as $groupKey => $group) {
            foreach ($group['settings'] as $setting) {
                $output[] = [
                    'key' => $setting['key'],
                    'group' => $groupKey === '_ungrouped' ? null : $groupKey,
                    'type' => $setting['type'],
                    'name' => $setting['name'],
                    'value' => $this->values[$groupKey][$setting['key']] ?? null,
                ];
            }
        }

        return json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

    public function openImportModal(): void
    {
        $this->importJson = '';
        $this->showImportModal = true;
    }

    public function importSettings(): void
    {
        $this->validate([
            'importJson' => 'required|json',
        ]);

        $count = Tardis::settings()->import($this->importJson);
        $this->showImportModal = false;
        $this->loadSettings();
        $this->saved = true;
    }

    public function openExportModal(): void
    {
        $this->exportJson = Tardis::settings()->export();
        $this->showExportModal = true;
    }

    protected function resetNewSettingFields(): void
    {
        $this->newKey = '';
        $this->newGroup = '';
        $this->newType = 'text';
        $this->newName = '';
        $this->newInfo = '';
        $this->newDefaultValue = null;
        $this->newValidation = '';
    }

    protected function groupAndKey(string $fullKey): array
    {
        $parts = explode('.', $fullKey, 2);

        return count($parts) === 2 ? $parts : ['_ungrouped', $parts[0]];
    }

    public function addDynamicRow(string $fullKey): void
    {
        [$group, $key] = $this->groupAndKey($fullKey);
        $value = $this->values[$group][$key] ?? [];
        if (! is_array($value)) {
            $value = [];
        }
        $value[] = ['key' => '', 'value' => ''];
        $this->values[$group][$key] = $value;
    }

    public function removeDynamicRow(string $fullKey, int $index): void
    {
        [$group, $key] = $this->groupAndKey($fullKey);
        $value = $this->values[$group][$key] ?? [];
        if (is_array($value) && isset($value[$index])) {
            unset($value[$index]);
            $this->values[$group][$key] = array_values($value);
        }
    }

    public function addSimpleArrayItem(string $fullKey): void
    {
        [$group, $key] = $this->groupAndKey($fullKey);
        $value = $this->values[$group][$key] ?? [];
        if (! is_array($value)) {
            $value = [];
        }
        $value[] = '';
        $this->values[$group][$key] = $value;
    }

    public function removeSimpleArrayItem(string $fullKey, int $index): void
    {
        [$group, $key] = $this->groupAndKey($fullKey);
        $value = $this->values[$group][$key] ?? [];
        if (is_array($value) && isset($value[$index])) {
            unset($value[$index]);
            $this->values[$group][$key] = array_values($value);
        }
    }

    protected function groupIcon(string $group): string
    {
        return match ($group) {
            'admin' => 'cog-6-tooth',
            'media' => 'photo',
            'plugins' => 'puzzle-piece',
            'bread' => 'database',
            'auth' => 'lock-closed',
            default => 'cog-6-tooth',
        };
    }

    public function getAvailableTypes(): array
    {
        return Setting::availableTypes();
    }
};
