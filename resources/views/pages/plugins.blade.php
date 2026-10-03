<?php

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Tardis\Auth\Abilities;
use Tardis\Auth\BreadAuthorization;
use Tardis\Facades\Tardis;

new #[Title('tardis::plugins.plugin_manager')] #[Layout('tardis::layouts.admin')] class extends Component
{
    public array $plugins = [];

    public int $enabledCount = 0;

    public ?string $message = null;

    /**
     * Runs on every request, not only on mount: Livewire keeps component state
     * between updates, so a permission revoked after the page opened must
     * still stop the next action.
     */
    public function boot(): void
    {
        app(BreadAuthorization::class)->authorizeAbility(Abilities::PLUGINS);
    }

    public function mount(): void
    {
        $this->refreshPlugins();
    }

    public function refreshPlugins(): void
    {
        $this->plugins = Tardis::plugins()->all()->map(function ($plugin, $name) {
            $instance = $plugin['instance'];
            $info = Tardis::plugins()->getPluginInfo($name);

            return [
                'name' => $instance->name(),
                'slug' => $name,
                'type' => $plugin['type'],
                'description' => method_exists($instance, 'description') ? $instance->description() : null,
                'enabled' => Tardis::plugins()->isEnabled($name),
                'locked' => Tardis::plugins()->isLocked($name),
                'version' => $info['version'] ?? null,
            ];
        })->toArray();

        $this->enabledCount = Tardis::plugins()->enabled()->count();
    }

    public function enable(string $name): void
    {
        Tardis::plugins()->enable($name);
        $this->refreshPlugins();
    }

    public function disable(string $name): void
    {
        try {
            Tardis::plugins()->disable($name);
            $this->message = null;
        } catch (\LogicException) {
            $this->message = __('tardis::plugins.cannot_disable');
        }

        $this->refreshPlugins();
    }
}; ?>

@php
$typeLabels = [
    'authentication' => ['label' => __('tardis::plugins.types.authentication'), 'class' => 'badge-primary'],
    'authorization' => ['label' => __('tardis::plugins.types.authorization'), 'class' => 'badge-secondary'],
    'formfield' => ['label' => __('tardis::plugins.types.formfield'), 'class' => 'badge-accent'],
    'theme' => ['label' => __('tardis::plugins.types.theme'), 'class' => 'badge-info'],
    'generic' => ['label' => __('tardis::plugins.types.generic'), 'class' => 'badge-ghost'],
    'unknown' => ['label' => __('tardis::plugins.types.unknown'), 'class' => 'badge-neutral'],
];
@endphp

<div>
    <x-tardis::page-header
        :title="__('tardis::plugins.plugin_manager')"
        :description="__('tardis::plugins.summary', ['registered' => count($plugins), 'enabled' => $enabledCount])"
    />

    @if ($message)
        <div class="alert alert-warning mb-4" role="alert">
            <span>{{ $message }}</span>
        </div>
    @endif

    @if (empty($plugins))
        <div class="card bg-base-100 border border-base-300">
            <div class="card-body text-center py-12">
                <x-tardis::icon name="puzzle-piece" class="w-16 h-16 mx-auto text-base-content/30" />
                <h3 class="text-lg font-semibold mt-4">{{ __('tardis::plugins.no_plugins_installed') }}</h3>
                <p class="text-base-content/60 mt-2">
                    {{ __('tardis::plugins.install_via') }} <code class="badge badge-ghost">composer require tardis/plugin-name</code>
                </p>
            </div>
        </div>
    @else
        <div class="card bg-base-100 border border-base-300">
            <div class="overflow-x-auto">
                <table class="table">
                    <thead>
                        <tr>
                            <th scope="col">{{ __('tardis::plugins.plugin') }}</th>
                            <th scope="col">{{ __('tardis::plugins.type') }}</th>
                            <th scope="col">{{ __('tardis::plugins.status') }}</th>
                            <th class="text-right" scope="col">{{ __('tardis::plugins.actions') }}</th>
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
                                    @if ($plugin['version'])
                                        <div class="text-xs text-primary">v{{ $plugin['version'] }}</div>
                                    @endif
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
                                            <x-tardis::icon name="check-circle" class="w-3 h-3" />
                                            {{ __('tardis::plugins.enabled') }}
                                        </span>
                                    @else
                                        <span class="badge badge-ghost badge-sm gap-1">
                                            <x-tardis::icon name="x-circle" class="w-3 h-3" />
                                            {{ __('tardis::plugins.disabled') }}
                                        </span>
                                    @endif
                                </td>
                                <td class="text-right">
                                    @if ($plugin['locked'])
                                        <span class="badge badge-ghost badge-sm gap-1">
                                            <x-tardis::icon name="lock-closed" class="w-3 h-3" />
                                            {{ __('tardis::plugins.required') }}
                                        </span>
                                    @elseif ($plugin['enabled'])
                                        <button
                                            wire:click="disable('{{ $plugin['slug'] }}')"
                                            class="btn btn-ghost btn-sm text-error"
                                        >
                                            <x-tardis::icon name="power" class="w-4 h-4" />
                                            {{ __('tardis::plugins.disable') }}
                                        </button>
                                    @else
                                        <button
                                            wire:click="enable('{{ $plugin['slug'] }}')"
                                            class="btn btn-ghost btn-sm text-success"
                                        >
                                            <x-tardis::icon name="power" class="w-4 h-4" />
                                            {{ __('tardis::plugins.enable') }}
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
