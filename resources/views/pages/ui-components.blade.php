<?php

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('UI Components')] #[Layout('tardis::layouts.admin')] class extends Component
{
    public function render()
    {
        return view('tardis::pages.ui-components');
    }
}; ?>

<div class="mb-8">
    <h1 class="text-2xl font-bold">UI Components</h1>
    <p class="text-base-content/60 mt-1">Available UI components for your application</p>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    <div class="card bg-base-100 shadow">
        <div class="card-body">
            <h2 class="card-title">Buttons</h2>
            <div class="flex flex-wrap gap-2 mt-2">
                <button class="btn btn-primary">Primary</button>
                <button class="btn btn-secondary">Secondary</button>
                <button class="btn btn-accent">Accent</button>
                <button class="btn btn-ghost">Ghost</button>
                <button class="btn btn-outline">Outline</button>
                <button class="btn btn-soft">Soft</button>
                <button class="btn btn-dash">Dash</button>
            </div>
            <div class="flex flex-wrap gap-2 mt-2">
                <button class="btn btn-sm">Small</button>
                <button class="btn">Normal</button>
                <button class="btn btn-lg">Large</button>
                <button class="btn btn-xl">XL</button>
            </div>
        </div>
    </div>

    <div class="card bg-base-100 shadow">
        <div class="card-body">
            <h2 class="card-title">Alerts</h2>
            <div class="flex flex-col gap-2 mt-2">
                <div class="alert alert-info">Info alert</div>
                <div class="alert alert-success">Success alert</div>
                <div class="alert alert-warning">Warning alert</div>
                <div class="alert alert-error">Error alert</div>
            </div>
        </div>
    </div>

    <div class="card bg-base-100 shadow">
        <div class="card-body">
            <h2 class="card-title">Form Elements</h2>
            <div class="flex flex-col gap-3 mt-2">
                <label class="input input-bordered flex items-center gap-2">
                    Text
                    <input type="text" class="grow" placeholder="Type here" />
                </label>
                <select class="select select-bordered">
                    <option>Option 1</option>
                    <option>Option 2</option>
                </select>
                <label class="flex items-center gap-2">
                    <input type="checkbox" class="checkbox" checked />
                    Checkbox
                </label>
                <label class="flex items-center gap-2">
                    <input type="radio" name="radio" class="radio" checked />
                    Radio
                </label>
                <input type="range" class="range" />
                <input type="text" placeholder="Disabled" class="input input-bordered" disabled />
            </div>
        </div>
    </div>

    <div class="card bg-base-100 shadow">
        <div class="card-body">
            <h2 class="card-title">Badges</h2>
            <div class="flex flex-wrap gap-2 mt-2">
                <span class="badge">Default</span>
                <span class="badge badge-neutral">Neutral</span>
                <span class="badge badge-primary">Primary</span>
                <span class="badge badge-secondary">Secondary</span>
                <span class="badge badge-accent">Accent</span>
                <span class="badge badge-info">Info</span>
                <span class="badge badge-success">Success</span>
                <span class="badge badge-warning">Warning</span>
                <span class="badge badge-error">Error</span>
            </div>
            <div class="flex flex-wrap gap-2 mt-3">
                <span class="badge badge-outline">Outline</span>
                <span class="badge badge-soft">Soft</span>
                <span class="badge badge-dash">Dash</span>
                <span class="badge badge-sm">Small</span>
                <span class="badge badge-lg">Large</span>
            </div>
        </div>
    </div>

    <div class="card bg-base-100 shadow">
        <div class="card-body">
            <h2 class="card-title">Loading</h2>
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

    <div class="card bg-base-100 shadow">
        <div class="card-body">
            <h2 class="card-title">Toggles</h2>
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

    <div class="card bg-base-100 shadow">
        <div class="card-body">
            <h2 class="card-title">Progress</h2>
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

    <div class="card bg-base-100 shadow">
        <div class="card-body">
            <h2 class="card-title">Stats</h2>
            <div class="stats shadow mt-2">
                <div class="stat">
                    <div class="stat-title">Total Users</div>
                    <div class="stat-value">1,234</div>
                    <div class="stat-desc">↗︎ 12% increase</div>
                </div>
                <div class="stat">
                    <div class="stat-title">Revenue</div>
                    <div class="stat-value">$45.6K</div>
                    <div class="stat-desc">↘︎ 3% decrease</div>
                </div>
            </div>
        </div>
    </div>
</div>
