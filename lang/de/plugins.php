<?php

return [
    'plugin_manager' => 'Plugin-Verwaltung',
    'no_plugins_installed' => 'Keine Plugins installiert',
    'plugin' => 'Plugin',
    'type' => 'Typ',
    'status' => 'Status',
    'actions' => 'Aktionen',
    'enabled' => 'Aktiviert',
    'disabled' => 'Deaktiviert',
    'required' => 'Erforderlich',
    'disable' => 'Deaktivieren',
    'enable' => 'Aktivieren',
    'cannot_disable' => 'Dieses Plugin schützt das Panel und kann nicht deaktiviert werden.',
    'install_via' => 'Plugins installieren über',
    'summary' => ':registered Plugin(s) registriert · :enabled aktiviert',
    'types' => [
        'authentication' => 'Authentifizierung',
        'authorization' => 'Berechtigung',
        'formfield' => 'Formularfeld',
        'theme' => 'Design',
        'generic' => 'Allgemein',
        'unknown' => 'Unbekannt',
    ],
    'settings' => 'Einstellungen',
    'labels' => [
        'authentication' => 'Sitzungsanmeldung',
        'authorization' => 'Rollenbasierter Zugriff',
    ],
    'descriptions' => [
        'authentication' => 'Meldet mit dem Session-Guard und dem Benutzermodell der Host-Anwendung an.',
        'authorization' => 'Rollen und Berechtigungen, gespeichert in den Tardis-Tabellen.',
    ],
];
