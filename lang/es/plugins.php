<?php

return [
    'plugin_manager' => 'Gestor de plugins',
    'no_plugins_installed' => 'No hay plugins instalados',
    'plugin' => 'Plugin',
    'type' => 'Tipo',
    'status' => 'Estado',
    'actions' => 'Acciones',
    'enabled' => 'Activado',
    'disabled' => 'Desactivado',
    'required' => 'Obligatorio',
    'disable' => 'Desactivar',
    'enable' => 'Activar',
    'cannot_disable' => 'Este plugin protege el panel y no se puede desactivar.',
    'install_via' => 'Instala plugins mediante',
    'summary' => ':registered plugin(s) registrado(s) · :enabled activado(s)',
    'types' => [
        'authentication' => 'Autenticación',
        'authorization' => 'Permisos',
        'formfield' => 'Campo de formulario',
        'theme' => 'Tema',
        'generic' => 'Genérico',
        'unknown' => 'Desconocido',
    ],
    'settings' => 'Ajustes',
    'labels' => [
        'authentication' => 'Inicio de sesión por sesión',
        'authorization' => 'Acceso basado en roles',
    ],
    'descriptions' => [
        'authentication' => 'Inicia sesión con el guard de sesión del anfitrión y su modelo de usuario.',
        'authorization' => 'Roles y permisos almacenados en las tablas de Tardis.',
    ],
];
