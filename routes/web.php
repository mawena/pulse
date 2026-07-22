<?php

use Illuminate\Support\Facades\Route;

// SPA Vue 3 : toutes les routes non-API sont gérées par vue-router.
Route::view('/{any?}', 'app')->where('any', '.*');
