<?php

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('UI Components')] #[Layout('tardis::layouts.admin')] class extends Component
{
}; ?>

<div>
    <div class="mb-8">
        <h1 class="text-2xl font-bold">{{ __('tardis::ui.ui_components') }}</h1>
        <p class="text-base-content/60 mt-1">{{ __('tardis::ui.available_ui_components_for_your_applica_50d1') }}</p>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    <div class="card bg-base-100 border border-base-300">
        <div class="card-body">
            <h2 class="card-title">{{ __('tardis::ui.buttons') }}</h2>
            <div class="flex flex-wrap gap-2 mt-2">
                <button class="btn btn-primary">{{ __('tardis::ui.primary') }}</button>
                <button class="btn btn-secondary">{{ __('tardis::ui.secondary') }}</button>
                <button class="btn btn-accent">{{ __('tardis::ui.accent') }}</button>
                <button class="btn btn-ghost">{{ __('tardis::ui.ghost') }}</button>
                <button class="btn btn-outline">{{ __('tardis::ui.outline') }}</button>
                <button class="btn btn-soft">{{ __('tardis::ui.soft') }}</button>
                <button class="btn btn-dash">{{ __('tardis::ui.dash') }}</button>
            </div>
            <div class="flex flex-wrap gap-2 mt-2">
                <button class="btn btn-sm">{{ __('tardis::ui.small') }}</button>
                <button class="btn">{{ __('tardis::ui.normal') }}</button>
                <button class="btn btn-lg">{{ __('tardis::ui.large') }}</button>
                <button class="btn btn-xl">{{ __('tardis::ui.xl') }}</button>
            </div>
        </div>
    </div>

    <div class="card bg-base-100 border border-base-300">
        <div class="card-body">
            <h2 class="card-title">{{ __('tardis::ui.alerts') }}</h2>
            <div class="flex flex-col gap-2 mt-2">
                <div class="alert alert-info">{{ __('tardis::ui.info_alert') }}</div>
                <div class="alert alert-success">{{ __('tardis::ui.success_alert') }}</div>
                <div class="alert alert-warning">{{ __('tardis::ui.warning_alert') }}</div>
                <div class="alert alert-error">{{ __('tardis::ui.error_alert') }}</div>
            </div>
        </div>
    </div>

    <div class="card bg-base-100 border border-base-300">
        <div class="card-body">
            <h2 class="card-title">{{ __('tardis::ui.form_elements') }}</h2>
            <div class="flex flex-col gap-3 mt-2">
                <label class="input flex items-center gap-2">
                    {{ __('tardis::ui.text') }}
                    <input type="text" class="grow" placeholder="{{ __('tardis::ui.type_here') }}" />
                </label>
                <select class="select">
                    <option>{{ __('tardis::ui.option_1') }}</option>
                    <option>{{ __('tardis::ui.option_2') }}</option>
                </select>
                <label class="flex items-center gap-2">
                    <input type="checkbox" class="checkbox" checked />
                    {{ __('tardis::ui.checkbox') }}
                </label>
                <label class="flex items-center gap-2">
                    <input type="radio" name="radio" class="radio" checked />
                    {{ __('tardis::ui.radio') }}
                </label>
                <input type="range" class="range" />
                <input type="text" placeholder="{{ __('tardis::ui.disabled') }}" class="input" disabled />
            </div>
        </div>
    </div>

    <div class="card bg-base-100 border border-base-300">
        <div class="card-body">
            <h2 class="card-title">{{ __('tardis::ui.badges') }}</h2>
            <div class="flex flex-wrap gap-2 mt-2">
                <span class="badge">{{ __('tardis::ui.default') }}</span>
                <span class="badge badge-neutral">{{ __('tardis::ui.neutral') }}</span>
                <span class="badge badge-primary">{{ __('tardis::ui.primary') }}</span>
                <span class="badge badge-secondary">{{ __('tardis::ui.secondary') }}</span>
                <span class="badge badge-accent">{{ __('tardis::ui.accent') }}</span>
                <span class="badge badge-info">{{ __('tardis::ui.info') }}</span>
                <span class="badge badge-success">{{ __('tardis::ui.success') }}</span>
                <span class="badge badge-warning">{{ __('tardis::ui.warning') }}</span>
                <span class="badge badge-error">{{ __('tardis::ui.error') }}</span>
            </div>
            <div class="flex flex-wrap gap-2 mt-3">
                <span class="badge badge-outline">{{ __('tardis::ui.outline') }}</span>
                <span class="badge badge-soft">{{ __('tardis::ui.soft') }}</span>
                <span class="badge badge-dash">{{ __('tardis::ui.dash') }}</span>
                <span class="badge badge-sm">{{ __('tardis::ui.small') }}</span>
                <span class="badge badge-lg">{{ __('tardis::ui.large') }}</span>
            </div>
        </div>
    </div>

    <div class="card bg-base-100 border border-base-300">
        <div class="card-body">
            <h2 class="card-title">{{ __('tardis::ui.loading') }}</h2>
            <div class="flex flex-wrap gap-2 mt-2 items-center">
                <span class="loading loading-spinner loading-xs"></span>
                <span class="loading loading-spinner loading-sm"></span>
                <span class="loading loading-spinner loading-md"></span>
                <span class="loading loading-spinner loading-lg"></span>
            </div>
            <div class="flex flex-wrap gap-2 mt-2">
                <span class="loading loading-dots loading-sm"></span>
                <span class="loading loading-ring loading-sm"></span>
                <span class="loading loading-ball loading-sm"></span>
                <span class="loading loading-bars loading-sm"></span>
                <span class="loading loading-infinity loading-sm"></span>
            </div>
        </div>
    </div>

    <div class="card bg-base-100 border border-base-300">
        <div class="card-body">
            <h2 class="card-title">{{ __('tardis::ui.toggles') }}</h2>
            <div class="flex flex-wrap gap-2 mt-2 items-center">
                <input type="checkbox" class="toggle" checked />
                <input type="checkbox" class="toggle toggle-primary" checked />
                <input type="checkbox" class="toggle toggle-secondary" checked />
                <input type="checkbox" class="toggle toggle-accent" checked />
                <input type="checkbox" class="toggle toggle-sm" checked />
                <input type="checkbox" class="toggle toggle-lg" checked />
            </div>
        </div>
    </div>

    <div class="card bg-base-100 border border-base-300">
        <div class="card-body">
            <h2 class="card-title">{{ __('tardis::ui.progress') }}</h2>
            <div class="flex flex-col gap-2 mt-2">
                <progress class="progress w-full" value="20" max="100"></progress>
                <progress class="progress progress-primary w-full" value="40" max="100"></progress>
                <progress class="progress progress-secondary w-full" value="60" max="100"></progress>
                <progress class="progress progress-accent w-full" value="80" max="100"></progress>
                <progress class="progress progress-info w-full" value="100" max="100"></progress>
                <progress class="progress progress-success w-full" value="30" max="100"></progress>
                <progress class="progress progress-warning w-full" value="50" max="100"></progress>
                <progress class="progress progress-error w-full" value="70" max="100"></progress>
            </div>
        </div>
    </div>

    <div class="card bg-base-100 border border-base-300">
        <div class="card-body">
            <h2 class="card-title">{{ __('tardis::ui.stats') }}</h2>
            <div class="stats mt-2">
                <div class="stat">
                    <div class="stat-title">{{ __('tardis::ui.total_users') }}</div>
                    <div class="stat-value">1,234</div>
                    <div class="stat-desc">{{ __('tardis::ui.12_increase') }}</div>
                </div>
                <div class="stat">
                    <div class="stat-title">{{ __('tardis::ui.revenue') }}</div>
                    <div class="stat-value">$45.6K</div>
                    <div class="stat-desc">{{ __('tardis::ui.3_decrease') }}</div>
                </div>
            </div>
        </div>
    </div>
</div>
</div>
