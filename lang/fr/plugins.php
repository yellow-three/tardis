<?php

return [
    'plugin_manager' => 'Gestionnaire de plugins',
    'no_plugins_installed' => 'Aucun plugin installé',
    'plugin' => 'Plugin',
    'type' => 'Type',
    'status' => 'Statut',
    'actions' => 'Actions',
    'enabled' => 'Activé',
    'disabled' => 'Désactivé',
    'required' => 'Requis',
    'disable' => 'Désactiver',
    'enable' => 'Activer',
    'cannot_disable' => 'Ce plugin protège le panneau et ne peut pas être désactivé.',
    'install_via' => 'Installez des plugins via',
    'summary' => ':registered plugin(s) enregistré(s) · :enabled activé(s)',
    'types' => [
        'authentication' => 'Authentification',
        'authorization' => 'Permission',
        'formfield' => 'Champ de formulaire',
        'theme' => 'Thème',
        'generic' => 'Générique',
        'unknown' => 'Inconnu',
    ],
    'settings' => 'Paramètres',
    'labels' => [
        'authentication' => 'Connexion par session',
        'authorization' => 'Accès basé sur les rôles',
    ],
    'descriptions' => [
        'authentication' => "Connecte les utilisateurs avec le garde de session de l'hôte et son modèle d'utilisateur.",
        'authorization' => 'Rôles et permissions stockés dans les tables de Tardis.',
    ],
];
