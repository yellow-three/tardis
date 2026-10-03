<?php

namespace Tardis\Contracts\Plugins\Features\Provider;

/**
 * A plugin's own settings screen: the name of a Livewire component (for
 * example `tardis-blog::settings`). The Plugins page shows a Settings button
 * for an enabled plugin that implements this and opens the component in a
 * dialog. The component should guard itself the way any Tardis page does.
 */
interface SettingsComponent
{
    public function settingsComponent(): string;
}
