<?php

return [
    'plugin_manager' => 'Plugin Manager',
    'no_plugins_installed' => 'No plugins installed',
    'plugin' => 'Plugin',
    'type' => 'Type',
    'status' => 'Status',
    'actions' => 'Actions',
    'enabled' => 'Enabled',
    'disabled' => 'Disabled',
    'required' => 'Required',
    'disable' => 'Disable',
    'enable' => 'Enable',
    'cannot_disable' => 'This plugin protects the panel and cannot be disabled.',
    'install_via' => 'Install plugins via',
    'summary' => ':registered plugin(s) registered · :enabled enabled',
    'types' => [
        'authentication' => 'Auth',
        'authorization' => 'Permission',
        'formfield' => 'Formfield',
        'theme' => 'Theme',
        'generic' => 'Generic',
        'unknown' => 'Unknown',
    ],
    'settings' => 'Settings',
    'labels' => [
        'authentication' => 'Session sign-in',
        'authorization' => 'Role-based access',
    ],
    'descriptions' => [
        'authentication' => "Signs in with the host's session guard and its user model.",
        'authorization' => 'Roles and permissions stored in the Tardis tables.',
    ],
];
