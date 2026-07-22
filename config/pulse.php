<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Services LNMP supervisés
    |--------------------------------------------------------------------------
    | Clé = identifiant exposé par l'API (whitelist stricte),
    | valeur = nom de l'unité systemd correspondante.
    | Les noms d'unités sont configurables via .env pour s'adapter au VPS
    | (ex: php8.3-fpm vs php8.4-fpm selon la stack mawena/lnmp installée).
    */
    'services' => [
        'nginx' => env('PULSE_SERVICE_NGINX', 'nginx'),
        'mysql' => env('PULSE_SERVICE_MYSQL', 'mysql'),
        'php-fpm' => env('PULSE_SERVICE_PHP_FPM', 'php8.4-fpm'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Actions autorisées sur les services (whitelist stricte)
    |--------------------------------------------------------------------------
    */
    'service_actions' => ['restart', 'reload'],

    /*
    |--------------------------------------------------------------------------
    | Signaux autorisés pour kill (whitelist stricte)
    |--------------------------------------------------------------------------
    */
    'kill_signals' => ['TERM', 'KILL'],

    /*
    |--------------------------------------------------------------------------
    | Wrappers sudo (production VPS)
    |--------------------------------------------------------------------------
    | En production, les actions privilégiées passent par des wrappers dédiés
    | (/usr/local/bin/pulse-kill, /usr/local/bin/pulse-service) autorisés via
    | /etc/sudoers.d/mawenapulse. En dev local, les commandes sont exécutées
    | directement avec les droits de l'utilisateur courant.
    */
    'use_sudo' => env('PULSE_USE_SUDO', false),
    'sudo_kill_wrapper' => env('PULSE_SUDO_KILL', '/usr/local/bin/pulse-kill'),
    'sudo_service_wrapper' => env('PULSE_SUDO_SERVICE', '/usr/local/bin/pulse-service'),

];
