<?php

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

    public bool $showAddModal = false;

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

        $settings = Tardis::settings()->all();
        foreach ($settings as $setting) {
            $group = $setting->group ?? '_ungrouped';
            $key = $setting->key;

            if (isset($this->values[$group][$key])) {
                $data[$setting->getFullKey()] = $this->values[$group][$key];
            }
        }

        Tardis::settings()->update($data);
        $this->saved = true;
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
}; ?>

<div>
    <!-- Header -->
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold">Settings</h1>
            <p class="text-base-content/60 mt-1">Manage your application configuration</p>
        </div>

        <div class="flex gap-2">
            <button wire:click="openImportModal" class="btn btn-outline gap-2">
                <x-tardis::icon name="folder" class="w-4 h-4" />
                Import
            </button>
            <button wire:click="openExportModal" class="btn btn-outline gap-2">
                <x-tardis::icon name="check" class="w-4 h-4" />
                Export
            </button>
            <button wire:click="$set('showAddModal', true)" class="btn btn-primary gap-2">
                <x-tardis::icon name="plus" class="w-4 h-4" />
                Add Setting
            </button>
        </div>
    </div>

    @if ($saved)
        <div class="alert alert-success mb-6 shadow-sm">
            <x-tardis::icon name="check-circle" class="w-5 h-5" />
            <span>Settings saved successfully.</span>
        </div>
    @endif

    <!-- Horizontal Group Tabs (Voyager II style) -->
    @if (count($groups) > 0)
        <div class="tabs tabs-box mb-6 bg-base-100 shadow-sm overflow-x-auto">
            @foreach ($groups as $groupKey => $group)
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
        @if ($activeGroup && isset($groups[$activeGroup]))
            <div class="card bg-base-100 shadow-sm">
                <div class="card-body">
                    <h2 class="card-title text-xl flex items-center gap-2">
                        @if ($groups[$activeGroup]['icon'])
                            <x-tardis::icon :name="$groups[$activeGroup]['icon']" class="w-5 h-5" />
                        @endif
                        {{ $groups[$activeGroup]['label'] }}
                        <span class="text-sm text-base-content/40 font-normal">settings</span>
                    </h2>

                    <div class="divider mt-2 mb-0"></div>

                    <div class="space-y-1">
                        @foreach ($groups[$activeGroup]['settings'] as $setting)
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
                                                translatable
                                            </span>
                                        @endif
                                        @if (!empty($setting['validation']))
                                            <span class="badge badge-ghost badge-xs gap-1">
                                                <x-tardis::icon name="check-circle" class="w-3 h-3" />
                                                validated
                                            </span>
                                        @endif
                                        <button
                                            wire:click="confirmDelete('{{ $setting['fullKey'] }}')"
                                            class="btn btn-ghost btn-xs text-error ml-auto"
                                            title="Delete setting"
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
                                            class="input input-bordered w-full"
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
                                            class="textarea textarea-bordered w-full"
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
                                            class="input input-bordered w-full"
                                        />

                                    {{-- Password --}}
                                    @elseif ($setting['type'] === 'password')
                                        <input
                                            type="password"
                                            id="setting-{{ $setting['uuid'] }}"
                                            wire:model="values.{{ $activeGroup }}.{{ $setting['key'] }}"
                                            class="input input-bordered w-full"
                                        />

                                    {{-- Select --}}
                                    @elseif ($setting['type'] === 'select')
                                        <select
                                            id="setting-{{ $setting['uuid'] }}"
                                            wire:model="values.{{ $activeGroup }}.{{ $setting['key'] }}"
                                            class="select select-bordered w-full"
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
                                            class="input input-bordered w-full"
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
                                                        class="input input-bordered flex-1 input-sm"
                                                        placeholder="Item {{ $arrIndex + 1 }}"
                                                    />
                                                    <button
                                                        wire:click="removeSimpleArrayItem('{{ $setting['fullKey'] }}', {{ $arrIndex }})"
                                                        class="btn btn-ghost btn-square btn-sm text-error"
                                                        title="Remove item"
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
                                                Add item
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
                                                        placeholder="Key"
                                                        class="input input-bordered w-2/5 input-sm"
                                                    />
                                                    <input
                                                        type="text"
                                                        wire:model="values.{{ $activeGroup }}.{{ $setting['key'] }}.{{ $dynIndex }}.value"
                                                        placeholder="Value"
                                                        class="input input-bordered flex-1 input-sm"
                                                    />
                                                    <button
                                                        wire:click="removeDynamicRow('{{ $setting['fullKey'] }}', {{ $dynIndex }})"
                                                        class="btn btn-ghost btn-square btn-sm text-error"
                                                        title="Remove row"
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
                                                Add row
                                            </button>
                                        </div>

                                    {{-- Media Picker / Image --}}
                                    @elseif ($setting['type'] === 'media_picker' || $setting['type'] === 'image')
                                        <div class="flex gap-2 items-center">
                                            <input
                                                type="text"
                                                id="setting-{{ $setting['uuid'] }}"
                                                wire:model="values.{{ $activeGroup }}.{{ $setting['key'] }}"
                                                placeholder="Media path or URL"
                                                class="input input-bordered flex-1"
                                            />
                                            <button class="btn btn-outline btn-square" title="Browse media">
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
                                                placeholder="File path"
                                                class="input input-bordered flex-1"
                                            />
                                            <button class="btn btn-outline btn-square" title="Browse files">
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
                                            class="input input-bordered w-full"
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
                                            <span class="label-text-alt text-error flex items-center gap-1">
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
                            Save {{ $groups[$activeGroup]['label'] }} Settings
                        </button>
                    </div>
                </div>
            </div>
        @endif
    @else
        <!-- Empty State -->
        <div class="card bg-base-100 shadow-sm">
            <div class="card-body text-center py-16">
                <x-tardis::icon name="cog-6-tooth" class="w-16 h-16 mx-auto opacity-20" />
                <h3 class="text-lg font-semibold mt-4">No settings configured</h3>
                <p class="text-base-content/60 mt-1 max-w-md mx-auto">
                    Publish the default settings preset or add a new setting to get started.
                </p>
                <button wire:click="$set('showAddModal', true)" class="btn btn-primary gap-2 mt-4">
                    <x-tardis::icon name="plus" class="w-4 h-4" />
                    Add Setting
                </button>
            </div>
        </div>
    @endif

    <!-- Add Setting Modal -->
    @if ($showAddModal)
        <dialog class="modal modal-open">
            <div class="modal-box w-full max-w-lg">
                <h3 class="font-bold text-lg mb-4">Add New Setting</h3>

                <form wire:submit="createSetting" class="space-y-4">
                    <div class="form-control">
                        <label class="label">
                            <span class="label-text">Key <span class="text-error">*</span></span>
                        </label>
                        <input type="text" wire:model="newKey" class="input input-bordered" placeholder="e.g., site_name" />
                        @error('newKey')
                            <label class="label">
                                <span class="label-text-alt text-error">{{ $message }}</span>
                            </label>
                        @enderror
                    </div>

                    <div class="form-control">
                        <label class="label">
                            <span class="label-text">Group</span>
                        </label>
                        <input type="text" wire:model="newGroup" class="input input-bordered" placeholder="e.g., admin (optional)" />
                    </div>

                    <div class="form-control">
                        <label class="label">
                            <span class="label-text">Type <span class="text-error">*</span></span>
                        </label>
                        <select wire:model="newType" class="select select-bordered">
                            @foreach ($this->getAvailableTypes() as $typeValue => $typeLabel)
                                <option value="{{ $typeValue }}">{{ $typeLabel }}</option>
                            @endforeach
                        </select>
                        @error('newType')
                            <label class="label">
                                <span class="label-text-alt text-error">{{ $message }}</span>
                            </label>
                        @enderror
                    </div>

                    <div class="form-control">
                        <label class="label">
                            <span class="label-text">Label <span class="text-error">*</span></span>
                        </label>
                        <input type="text" wire:model="newName" class="input input-bordered" placeholder="e.g., Site Name" />
                        @error('newName')
                            <label class="label">
                                <span class="label-text-alt text-error">{{ $message }}</span>
                            </label>
                        @enderror
                    </div>

                    <div class="form-control">
                        <label class="label">
                            <span class="label-text">Description</span>
                        </label>
                        <input type="text" wire:model="newInfo" class="input input-bordered" placeholder="Optional description" />
                    </div>

                    <div class="form-control">
                        <label class="label">
                            <span class="label-text">Default Value</span>
                        </label>
                        <input type="text" wire:model="newDefaultValue" class="input input-bordered" placeholder="Optional default value" />
                    </div>

                    <div class="form-control">
                        <label class="label">
                            <span class="label-text">Validation Rules</span>
                        </label>
                        <input type="text" wire:model="newValidation" class="input input-bordered" placeholder="e.g., required|string|max:255" />
                        <label class="label">
                            <span class="label-text-alt text-base-content/50">Pipe-separated rules (optional)</span>
                        </label>
                    </div>

                    <div class="modal-action">
                        <button type="button" wire:click="$set('showAddModal', false)" class="btn btn-ghost">Cancel</button>
                        <button type="submit" class="btn btn-primary">Create Setting</button>
                    </div>
                </form>
            </div>
            <form method="dialog" class="modal-backdrop">
                <button wire:click="$set('showAddModal', false)">close</button>
            </form>
        </dialog>
    @endif

    <!-- Delete Confirmation Modal -->
    @if ($showDeleteModal)
        <dialog class="modal modal-open">
            <div class="modal-box">
                <h3 class="font-bold text-lg">Delete Setting</h3>
                <p class="py-4">Are you sure you want to delete the setting <strong>{{ $deleteKey }}</strong>? This action cannot be undone.</p>
                <div class="modal-action">
                    <button wire:click="cancelDelete" class="btn btn-ghost">Cancel</button>
                    <button wire:click="deleteSetting" class="btn btn-error">Delete</button>
                </div>
            </div>
            <form method="dialog" class="modal-backdrop">
                <button wire:click="cancelDelete">close</button>
            </form>
        </dialog>
    @endif

    <!-- Import Modal -->
    @if ($showImportModal)
        <dialog class="modal modal-open">
            <div class="modal-box w-full max-w-lg">
                <h3 class="font-bold text-lg mb-4">Import Settings</h3>

                <form wire:submit="importSettings" class="space-y-4">
                    <div class="form-control">
                        <label class="label">
                            <span class="label-text">JSON Data <span class="text-error">*</span></span>
                        </label>
                        <textarea
                            wire:model="importJson"
                            class="textarea textarea-bordered font-mono text-sm"
                            rows="10"
                            placeholder='[{"key": "setting_name", "type": "text", "name": "Setting Name", "value": "default"}]'
                        ></textarea>
                        @error('importJson')
                            <label class="label">
                                <span class="label-text-alt text-error">{{ $message }}</span>
                            </label>
                        @enderror
                    </div>

                    <div class="modal-action">
                        <button type="button" wire:click="$set('showImportModal', false)" class="btn btn-ghost">Cancel</button>
                        <button type="submit" class="btn btn-primary">Import</button>
                    </div>
                </form>
            </div>
            <form method="dialog" class="modal-backdrop">
                <button wire:click="$set('showImportModal', false)">close</button>
            </form>
        </dialog>
    @endif

    <!-- Export Modal -->
    @if ($showExportModal)
        <dialog class="modal modal-open">
            <div class="modal-box w-full max-w-lg">
                <h3 class="font-bold text-lg mb-4">Export Settings</h3>

                <div class="form-control">
                    <label class="label">
                        <span class="label-text">JSON Data</span>
                    </label>
                    <textarea
                        class="textarea textarea-bordered font-mono text-sm"
                        rows="10"
                        readonly
                    >{{ $exportJson }}</textarea>
                </div>

                <div class="modal-action">
                    <button type="button" wire:click="$set('showExportModal', false)" class="btn btn-ghost">Close</button>
                    <button type="button" onclick="navigator.clipboard.writeText(document.querySelector('[wire\\\\:model=exportJson]').value || document.querySelector('textarea[readonly]').value)" class="btn btn-primary">Copy to Clipboard</button>
                </div>
            </div>
            <form method="dialog" class="modal-backdrop">
                <button wire:click="$set('showExportModal', false)">close</button>
            </form>
        </dialog>
    @endif
</div>
