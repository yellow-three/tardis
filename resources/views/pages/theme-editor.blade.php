<?php

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Tardis\Auth\Abilities;
use Tardis\Auth\BreadAuthorization;
use Tardis\Manager\ThemeManager;
use Tardis\Theme\Theme;

new #[Title('tardis::theme_editor.title')] #[Layout('tardis::layouts.admin')] class extends Component
{
    public string $name = '';

    public string $label = '';

    public string $scheme = 'light';

    /** @var array<string, string> colour name => css colour */
    public array $colors = [];

    public bool $saved = false;

    /** Runs on every request: the ability can be revoked while the page is open. */
    public function boot(): void
    {
        app(BreadAuthorization::class)->authorizeAbility(Abilities::APPEARANCE);
    }

    public function mount(): void
    {
        $this->startNew();
    }

    public function startNew(): void
    {
        $this->load(app(ThemeManager::class)->find('tardis-light'));
        $this->name = '';
        $this->label = '';
        $this->saved = false;
        $this->resetErrorBag();
    }

    /** Edit a custom theme, or start a copy of any theme. */
    public function edit(string $name): void
    {
        $theme = app(ThemeManager::class)->find($name);

        if ($theme === null) {
            return;
        }

        $this->load($theme);
        $this->saved = false;
        $this->resetErrorBag();

        if ($theme->builtin) {
            $this->name = '';
            $this->label = '';
        }
    }

    public function delete(string $name): void
    {
        $theme = app(ThemeManager::class)->find($name);

        if ($theme !== null && ! $theme->builtin) {
            app(ThemeManager::class)->deleteCustom($name);
        }
    }

    public function save(): void
    {
        $this->resetErrorBag();

        $ok = app(ThemeManager::class)->saveCustom([
            'name' => $this->name,
            'label' => $this->label,
            'scheme' => $this->scheme,
            'colors' => array_filter(array_map('trim', $this->colors), fn ($value) => $value !== ''),
        ]);

        if (! $ok) {
            $this->addError('name', __('tardis::theme_editor.invalid'));
            $this->saved = false;

            return;
        }

        $this->saved = true;
    }

    protected function load(?Theme $theme): void
    {
        if ($theme === null) {
            return;
        }

        $this->name = $theme->name;
        $this->label = $theme->label;
        $this->scheme = $theme->scheme;
        $this->colors = $theme->colors;
    }

    public function themes()
    {
        return app(ThemeManager::class)->all();
    }
}; ?>

<div>
    <x-tardis::page-header :title="__('tardis::theme_editor.title')" :description="__('tardis::theme_editor.description')">
        <x-slot:action>
            <button type="button" wire:click="startNew" class="btn btn-primary">{{ __('tardis::theme_editor.new') }}</button>
        </x-slot:action>
    </x-tardis::page-header>

    <div class="grid gap-6 lg:grid-cols-2">
        <x-tardis::card :title="__('tardis::theme_editor.existing')">
            <ul class="divide-y divide-base-200">
                @foreach ($this->themes() as $theme)
                    <li wire:key="theme-{{ $theme->name }}" class="flex items-center gap-3 py-2">
                        <span class="flex gap-1">
                            @foreach ($theme->previewColors() as $color)
                                <span class="h-5 w-5 rounded-full border border-base-300" style="background-color: {{ $color }}"></span>
                            @endforeach
                        </span>
                        <span class="min-w-0 flex-1">
                            <span class="block truncate text-sm font-medium">{{ $theme->label }}</span>
                            <span class="text-xs text-base-content/50">{{ $theme->name }} · {{ __('tardis::theme_editor.'.$theme->scheme) }}</span>
                        </span>
                        @if ($theme->builtin)
                            <x-tardis::badge>{{ __('tardis::theme_editor.builtin') }}</x-tardis::badge>
                            <button type="button" wire:click="edit(@js($theme->name))" class="btn btn-ghost btn-xs">{{ __('tardis::theme_editor.duplicate') }}</button>
                        @else
                            <button type="button" wire:click="edit(@js($theme->name))" class="btn btn-ghost btn-xs">{{ __('tardis::theme_editor.edit') }}</button>
                            <button type="button" wire:click="delete(@js($theme->name))" wire:confirm="{{ __('tardis::theme_editor.confirm_delete') }}" class="btn btn-ghost btn-xs text-error">{{ __('tardis::theme_editor.delete') }}</button>
                        @endif
                    </li>
                @endforeach
            </ul>
        </x-tardis::card>

        <x-tardis::card :title="__('tardis::theme_editor.editor')">
            <form wire:submit="save" class="space-y-4">
                <label class="flex flex-col gap-1">
                    <span class="font-medium">{{ __('tardis::theme_editor.name') }}</span>
                    <input type="text" wire:model="name" class="input w-full" required pattern="[a-z0-9][a-z0-9-]*" />
                    @error('name') <span class="text-error text-sm">{{ $message }}</span> @enderror
                </label>
                <label class="flex flex-col gap-1">
                    <span class="font-medium">{{ __('tardis::theme_editor.label') }}</span>
                    <input type="text" wire:model="label" class="input w-full" />
                </label>
                <label class="flex flex-col gap-1">
                    <span class="font-medium">{{ __('tardis::theme_editor.scheme') }}</span>
                    <select wire:model="scheme" class="select w-full">
                        <option value="light">{{ __('tardis::theme_editor.light') }}</option>
                        <option value="dark">{{ __('tardis::theme_editor.dark') }}</option>
                    </select>
                </label>

                <fieldset>
                    <legend class="mb-2 font-medium">{{ __('tardis::theme_editor.colors') }}</legend>
                    <div class="grid gap-2 sm:grid-cols-2">
                        @foreach (\Tardis\Theme\Theme::COLORS as $colorName)
                            <label class="flex items-center gap-2">
                                @php($current = trim((string) ($colors[$colorName] ?? '')))
                                <span class="h-6 w-6 shrink-0 rounded border border-base-300" @if ($current !== '' && \Tardis\Theme\Theme::isSafeColor($current)) style="background-color: {{ $current }}" @endif></span>
                                <span class="w-32 shrink-0 text-xs">{{ $colorName }}</span>
                                <input type="text" wire:model.live.debounce.400ms="colors.{{ $colorName }}" class="input input-sm w-full font-mono" />
                            </label>
                        @endforeach
                    </div>
                </fieldset>

                <div class="flex items-center gap-3">
                    <button type="submit" class="btn btn-primary">{{ __('tardis::theme_editor.save') }}</button>
                    @if ($saved)
                        <span class="text-success text-sm" role="status">{{ __('tardis::theme_editor.saved') }}</span>
                    @endif
                </div>
            </form>
        </x-tardis::card>
    </div>
</div>
