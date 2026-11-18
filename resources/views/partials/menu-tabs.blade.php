<div role="tablist" class="tabs tabs-border mb-6">
    <a role="tab" href="{{ route('tardis.menus.index') }}" wire:navigate @class(['tab', 'tab-active' => $active === 'admin']) @if ($active === 'admin') aria-current="page" @endif>{{ __('tardis::site_menus.admin_tab') }}</a>
    <a role="tab" href="{{ route('tardis.site-menus.index') }}" wire:navigate @class(['tab', 'tab-active' => $active === 'site']) @if ($active === 'site') aria-current="page" @endif>{{ __('tardis::site_menus.site_tab') }}</a>
</div>
