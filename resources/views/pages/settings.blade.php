<?php

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Tardis\Classes\Setting;
use Tardis\Facades\Tardis;
use Tardis\Manager\PluginManager;

new #[Title('Settings')] #[Layout('tardis::layouts.admin')] class extends Component
{
    public array $settings = [];

    public array $groups = [];

    public array $values = [];

    public ?string $activeGroup = null;

    public bool $saved = false;

    public function mount(): void
    {
        $this->loadSettings();
    }

    public function loadSettings(): void
    {
        $allSettings = Tardis::settings()->all();
        $this->groups = [];

        foreach ($allSettings as $setting) {
            $group = $setting->group ?? '_ungrouped';
            if (! isset($this->groups[$group])) {
                $this->groups[$group] = [
                    'label' => $group === '_ungrouped' ? 'General' : $group,
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
                'validation' => $setting->validation,
                'fullKey' => $setting->getFullKey(),
            ];

            // Initialize values
            $this->values[$setting->getFullKey()] = $setting->displayValue();
        }

        // Set first group as active
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

        // Collect all settings
        $settings = Tardis::settings()->all();
        foreach ($settings as $setting) {
            $fullKey = $setting->getFullKey();
            if (array_key_exists($fullKey, $this->values)) {
                $data[$fullKey] = $this->values[$fullKey];
            }
        }

        Tardis::settings()->update($data);
        $this->saved = true;
    }

    public function addDynamicRow(string $fullKey): void
    {
        $value = $this->values[$fullKey] ?? [];
        if (! is_array($value)) {
            $value = [];
        }
        $value[] = ['key' => '', 'value' => ''];
        $this->values[$fullKey] = $value;
    }

    public function removeDynamicRow(string $fullKey, int $index): void
    {
        $value = $this->values[$fullKey] ?? [];
        if (is_array($value) && isset($value[$index])) {
            unset($value[$index]);
            $this->values[$fullKey] = array_values($value);
        }
    }

    public function addSimpleArrayItem(string $fullKey): void
    {
        $value = $this->values[$fullKey] ?? [];
        if (! is_array($value)) {
            $value = [];
        }
        $value[] = '';
        $this->values[$fullKey] = $value;
    }

    public function removeSimpleArrayItem(string $fullKey, int $index): void
    {
        $value = $this->values[$fullKey] ?? [];
        if (is_array($value) && isset($value[$index])) {
            unset($value[$index]);
            $this->values[$fullKey] = array_values($value);
        }
    }
}; ?>

<div>
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold">Settings</h1>
            <p class="text-base-content/60 mt-1">Manage your application settings</p>
        </div>

        <button wire:click="save" class="btn btn-primary gap-2">
            <x-tardis::icon name="check" class="w-4 h-4" />
            Save Settings
        </button>
    </div>

    @if ($saved)
        <div class="alert alert-success mb-6 shadow">
            <x-tardis::icon name="check-circle" class="w-5 h-5" />
            <span>Settings saved successfully.</span>
        </div>
    @endif

    <div class="flex gap-6">
        <!-- Settings Group Navigation -->
        <div class="w-56 shrink-0">
            <ul class="menu bg-base-100 rounded-box shadow">
                @foreach ($groups as $groupKey => $group)
                    <li>
                        <a wire:click="setActiveGroup('{{ $groupKey }}')"
                           class="{{ $activeGroup === $groupKey ? 'active' : '' }}"
                           role="button">
                            {{ $group['label'] }}
                            <span class="badge badge-ghost badge-sm">{{ count($group['settings']) }}</span>
                        </a>
                    </li>
                @endforeach
            </ul>
        </div>

        <!-- Settings Form -->
        <div class="flex-1">
            @if ($activeGroup && isset($groups[$activeGroup]))
                <div class="card bg-base-100 shadow">
                    <div class="card-body">
                        <h2 class="card-title">{{ $groups[$activeGroup]['label'] }}</h2>
                        <p class="text-sm text-base-content/60 mb-4">Configure {{ strtolower($groups[$activeGroup]['label']) }} settings</p>

                        <div class="space-y-6">
                            @foreach ($groups[$activeGroup]['settings'] as $setting)
                                <div class="form-control">
                                    <label class="label" for="setting-{{ $setting['uuid'] }}">
                                        <span class="label-text font-medium">{{ $setting['name'] }}</span>
                                        @if ($setting['translatable'])
                                            <span class="badge badge-ghost badge-xs"> translatable</span>
                                        @endif
                                    </label>

                                    @if ($setting['info'])
                                        <p class="text-xs text-base-content/50 mb-2">{{ $setting['info'] }}</p>
                                    @endif

                                    {{-- Text --}}
                                    @if ($setting['type'] === 'text' || $setting['type'] === 'email' || $setting['type'] === 'color')
                                        <input
                                            type="{{ $setting['type'] === 'color' ? 'color' : ($setting['type'] === 'email' ? 'email' : 'text') }}"
                                            id="setting-{{ $setting['uuid'] }}"
                                            wire:model="values.{{ $setting['fullKey'] }}"
                                            class="input input-bordered w-full {{ $setting['type'] === 'color' ? 'input-md h-12' : '' }}"
                                        />

                                    {{-- Textarea --}}
                                    @elseif ($setting['type'] === 'textarea')
                                        <textarea
                                            id="setting-{{ $setting['uuid'] }}"
                                            wire:model="values.{{ $setting['fullKey'] }}"
                                            class="textarea textarea-bordered w-full"
                                            rows="4"
                                        ></textarea>

                                    {{-- Number --}}
                                    @elseif ($setting['type'] === 'number')
                                        <input
                                            type="number"
                                            id="setting-{{ $setting['uuid'] }}"
                                            wire:model="values.{{ $setting['fullKey'] }}"
                                            class="input input-bordered w-full"
                                        />

                                    {{-- Password --}}
                                    @elseif ($setting['type'] === 'password')
                                        <input
                                            type="password"
                                            id="setting-{{ $setting['uuid'] }}"
                                            wire:model="values.{{ $setting['fullKey'] }}"
                                            class="input input-bordered w-full"
                                        />

                                    {{-- Select --}}
                                    @elseif ($setting['type'] === 'select')
                                        <select
                                            id="setting-{{ $setting['uuid'] }}"
                                            wire:model="values.{{ $setting['fullKey'] }}"
                                            class="select select-bordered w-full"
                                        >
                                            @if (!empty($setting['options']))
                                                @foreach ($setting['options'] as $optionValue => $optionLabel)
                                                    <option value="{{ $optionValue }}">{{ $optionLabel }}</option>
                                                @endforeach
                                            @endif
                                        </select>

                                    {{-- Toggle --}}
                                    @elseif ($setting['type'] === 'toggle')
                                        <div class="flex items-center gap-3">
                                            <input
                                                type="checkbox"
                                                id="setting-{{ $setting['uuid'] }}"
                                                wire:model="values.{{ $setting['fullKey'] }}"
                                                class="toggle toggle-primary"
                                                value="1"
                                            />
                                            <span class="text-sm text-base-content/60">
                                                {{ $this->values[$setting['fullKey']] ? 'Enabled' : 'Disabled' }}
                                            </span>
                                        </div>

                                    {{-- Date --}}
                                    @elseif ($setting['type'] === 'date')
                                        <input
                                            type="date"
                                            id="setting-{{ $setting['uuid'] }}"
                                            wire:model="values.{{ $setting['fullKey'] }}"
                                            class="input input-bordered w-full"
                                        />

                                    {{-- Simple Array --}}
                                    @elseif ($setting['type'] === 'simple_array')
                                        <div class="space-y-2">
                                            @php $arrIndex = 0; @endphp
                                            @foreach ((array) ($this->values[$setting['fullKey']] ?? []) as $arrIndex => $item)
                                                <div class="flex gap-2 items-center">
                                                    <input
                                                        type="text"
                                                        wire:model="values.{{ $setting['fullKey'] }}.{{ $arrIndex }}"
                                                        class="input input-bordered flex-1"
                                                    />
                                                    <button
                                                        wire:click="removeSimpleArrayItem('{{ $setting['fullKey'] }}', {{ $arrIndex }})"
                                                        class="btn btn-ghost btn-sm text-error"
                                                    >
                                                        <x-tardis::icon name="x-mark" class="w-4 h-4" />
                                                    </button>
                                                </div>
                                            @endforeach
                                            <button
                                                wire:click="addSimpleArrayItem('{{ $setting['fullKey'] }}')"
                                                class="btn btn-ghost btn-sm gap-1"
                                            >
                                                <x-tardis::icon name="plus" class="w-4 h-4" />
                                                Add item
                                            </button>
                                        </div>

                                    {{-- Dynamic Input --}}
                                    @elseif ($setting['type'] === 'dynamic_input')
                                        <div class="space-y-2">
                                            @php $dynIndex = 0; @endphp
                                            @foreach ((array) ($this->values[$setting['fullKey']] ?? []) as $dynIndex => $row)
                                                <div class="flex gap-2 items-center">
                                                    <input
                                                        type="text"
                                                        wire:model="values.{{ $setting['fullKey'] }}.{{ $dynIndex }}.key"
                                                        placeholder="Key"
                                                        class="input input-bordered w-1/3"
                                                    />
                                                    <input
                                                        type="text"
                                                        wire:model="values.{{ $setting['fullKey'] }}.{{ $dynIndex }}.value"
                                                        placeholder="Value"
                                                        class="input input-bordered flex-1"
                                                    />
                                                    <button
                                                        wire:click="removeDynamicRow('{{ $setting['fullKey'] }}', {{ $dynIndex }})"
                                                        class="btn btn-ghost btn-sm text-error"
                                                    >
                                                        <x-tardis::icon name="x-mark" class="w-4 h-4" />
                                                    </button>
                                                </div>
                                            @endforeach
                                            <button
                                                wire:click="addDynamicRow('{{ $setting['fullKey'] }}')"
                                                class="btn btn-ghost btn-sm gap-1"
                                            >
                                                <x-tardis::icon name="plus" class="w-4 h-4" />
                                                Add row
                                            </button>
                                        </div>

                                    {{-- Media Picker --}}
                                    @elseif ($setting['type'] === 'media_picker' || $setting['type'] === 'image')
                                        <div class="flex gap-3 items-center">
                                            <input
                                                type="text"
                                                id="setting-{{ $setting['uuid'] }}"
                                                wire:model="values.{{ $setting['fullKey'] }}"
                                                placeholder="Media path or URL"
                                                class="input input-bordered flex-1"
                                            />
                                            <button class="btn btn-ghost btn-sm">
                                                <x-tardis::icon name="folder" class="w-4 h-4" />
                                                Browse
                                            </button>
                                        </div>

                                    {{-- File --}}
                                    @elseif ($setting['type'] === 'file')
                                        <input
                                            type="text"
                                            id="setting-{{ $setting['uuid'] }}"
                                            wire:model="values.{{ $setting['fullKey'] }}"
                                            placeholder="File path"
                                            class="input input-bordered w-full"
                                        />

                                    {{-- Fallback --}}
                                    @else
                                        <input
                                            type="text"
                                            id="setting-{{ $setting['uuid'] }}"
                                            wire:model="values.{{ $setting['fullKey'] }}"
                                            class="input input-bordered w-full"
                                        />
                                    @endif

                                    @error("values.{{ $setting['fullKey'] }}")
                                        <label class="label">
                                            <span class="label-text-alt text-error">{{ $message }}</span>
                                        </label>
                                    @enderror
                                </div>
                            @endforeach
                        </div>

                        <div class="card-actions justify-end mt-6 pt-4 border-t border-base-200">
                            <button wire:click="save" class="btn btn-primary gap-2">
                                <x-tardis::icon name="check" class="w-4 h-4" />
                                Save Settings
                            </button>
                        </div>
                    </div>
                </div>
            @else
                <div class="card bg-base-100 shadow">
                    <div class="card-body text-center py-12">
                        <x-tardis::icon name="cog-6-tooth" class="w-16 h-16 mx-auto opacity-30" />
                        <h3 class="text-lg font-semibold mt-4">No settings found</h3>
                        <p class="text-base-content/60 mt-2">
                            Add settings presets using <code class="badge badge-ghost">php artisan tardis:settings</code>
                        </p>
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
