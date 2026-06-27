<?php

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Tardis\Facades\Tardis;

new #[Title('Plugin Manager')] #[Layout('tardis::components.admin-layout')] class extends \Livewire\Component
{
    public array $plugins = [];

    public int $enabledCount = 0;

    public function mount(): void
    {
        $this->refreshPlugins();
    }

    public function refreshPlugins(): void
    {
        $this->plugins = Tardis::all()->map(function ($plugin, $name) {
            $instance = $plugin['instance'];

            return [
                'name' => $instance->name(),
                'slug' => $name,
                'type' => $plugin['type'],
                'description' => method_exists($instance, 'description') ? $instance->description() : null,
                'enabled' => Tardis::isEnabled($name),
            ];
        })->toArray();

        $this->enabledCount = Tardis::enabled()->count();
    }

    public function enable(string $name): void
    {
        Tardis::enable($name);
        $this->refreshPlugins();
    }

    public function disable(string $name): void
    {
        Tardis::disable($name);
        $this->refreshPlugins();
    }
}; ?>

@php
$typeLabels = [
    'authentication' => ['label' => 'Auth', 'class' => 'badge-primary'],
    'authorization' => ['label' => 'Permission', 'class' => 'badge-secondary'],
    'formfield' => ['label' => 'Formfield', 'class' => 'badge-accent'],
    'theme' => ['label' => 'Theme', 'class' => 'badge-info'],
    'generic' => ['label' => 'Generic', 'class' => 'badge-ghost'],
    'unknown' => ['label' => 'Unknown', 'class' => 'badge-neutral'],
];
@endphp

<div>
    <div class="mb-6">
        <h1 class="text-2xl font-bold">Plugin Manager</h1>
        <p class="text-base-content/60 mt-1">
            {{ count($plugins) }} plugin(s) registered · {{ $enabledCount }} enabled
        </p>
    </div>

    @if (empty($plugins))
        <div class="card bg-base-100 shadow">
            <div class="card-body text-center py-12">
                <x-heroicon-o-puzzle-piece class="w-16 h-16 mx-auto opacity-30" />
                <h3 class="text-lg font-semibold mt-4">No plugins installed</h3>
                <p class="text-base-content/60 mt-2">
                    Install plugins via <code class="badge badge-ghost">composer require tardis/plugin-name</code>
                </p>
            </div>
        </div>
    @else
        <div class="card bg-base-100 shadow">
            <div class="overflow-x-auto">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Plugin</th>
                            <th>Type</th>
                            <th>Status</th>
                            <th class="text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($plugins as $plugin)
                            @php
                                $typeInfo = $typeLabels[$plugin['type']] ?? $typeLabels['unknown'];
                            @endphp
                            <tr class="{{ $plugin['enabled'] ? '' : 'opacity-50' }}">
                                <td>
                                    <div class="font-semibold">{{ $plugin['name'] }}</div>
                                    <div class="text-xs text-base-content/50">{{ $plugin['slug'] }}</div>
                                    @if ($plugin['description'])
                                        <div class="text-sm text-base-content/70 mt-1">
                                            {{ $plugin['description'] }}
                                        </div>
                                    @endif
                                </td>
                                <td>
                                    <span class="badge {{ $typeInfo['class'] }} badge-sm">
                                        {{ $typeInfo['label'] }}
                                    </span>
                                </td>
                                <td>
                                    @if ($plugin['enabled'])
                                        <span class="badge badge-success badge-sm gap-1">
                                            <x-heroicon-o-check-circle class="w-3 h-3" />
                                            Enabled
                                        </span>
                                    @else
                                        <span class="badge badge-ghost badge-sm gap-1">
                                            <x-heroicon-o-x-circle class="w-3 h-3" />
                                            Disabled
                                        </span>
                                    @endif
                                </td>
                                <td class="text-right">
                                    @if ($plugin['enabled'])
                                        <button
                                            wire:click="disable('{{ $plugin['slug'] }}')"
                                            class="btn btn-ghost btn-sm text-error"
                                        >
                                            <x-heroicon-o-power class="w-4 h-4" />
                                            Disable
                                        </button>
                                    @else
                                        <button
                                            wire:click="enable('{{ $plugin['slug'] }}')"
                                            class="btn btn-ghost btn-sm text-success"
                                        >
                                            <x-heroicon-o-power class="w-4 h-4" />
                                            Enable
                                        </button>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>
