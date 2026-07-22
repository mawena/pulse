<?php

use Illuminate\Support\Facades\Route;

// SPA Vue 3 : toutes les routes non-API sont gérées par vue-router.
// La route nommée `login` évite un 500 (Route [login] not defined) quand un
// client non authentifié sans header JSON tape une route protégée.
Route::view('/login', 'app')->name('login');
Route::view('/{any?}', 'app')->where('any', '.*');
