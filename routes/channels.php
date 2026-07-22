<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// Métriques système temps réel (dashboard) — permission CASL maravel
Broadcast::channel('metrics', function ($user) {
    return $user->hasPermissionTo('read', 'system');
});

// État des services LNMP + systemd
Broadcast::channel('services', function ($user) {
    return $user->hasPermissionTo('read', 'service');
});
