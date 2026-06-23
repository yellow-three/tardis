<?php

return [

    'guard' => 'web',

    'redirects' => [
        'login' => '/dashboard',
        'logout' => '/login',
        'register' => '/dashboard',
    ],

    'features' => [
        'registration' => true,
        'password_reset' => true,
        'email_verification' => false,
        'two_factor' => false,
    ],

];
