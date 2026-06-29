<?php

declare(strict_types=1);

return [
    'aliases' => [
        'nav.dashboard' => 'heroicon-o-home',
        'nav.media' => 'heroicon-o-photo',
        'nav.ui' => 'heroicon-o-squares-2x2',
        'nav.settings' => 'heroicon-o-cog-6-tooth',
        'nav.plugins' => 'heroicon-o-puzzle-piece',
        'nav.bread' => 'heroicon-o-table-cells',
        'nav.permissions' => 'heroicon-o-lock-closed',
        'nav.roles' => 'heroicon-o-user-group',
        'action.create' => 'heroicon-o-plus',
        'action.edit' => 'heroicon-o-pencil-square',
        'action.delete' => 'heroicon-o-trash',
        'action.view' => 'heroicon-o-eye',
        'action.search' => 'heroicon-o-magnifying-glass',
        'auth.logout' => 'heroicon-o-arrow-right-on-rectangle',
        'ui.sun' => 'heroicon-o-sun',
        'ui.moon' => 'heroicon-o-moon',
        'ui.bell' => 'heroicon-o-bell',
        'ui.menu' => 'heroicon-o-bars-3',
        'ui.close' => 'heroicon-o-x-mark',

        /*
        |--------------------------------------------------------------------------
        | Raw-name fallbacks (used by icon picker / older templates)
        |--------------------------------------------------------------------------
        |
        | These map short icon names that don't follow the "heroicon-o-*" pattern
        | to their actual Heroicon outline equivalent. The <x-tardis::icon>
        | component auto-prefixes unknown names with "heroicon-o-", but icons
        | whose file name differs from the short name need an explicit alias.
        |
        */
        'database' => 'heroicon-o-circle-stack',
        'text' => 'heroicon-o-document-text',
        'toggle' => 'heroicon-o-adjustments-horizontal',
    ],
];
