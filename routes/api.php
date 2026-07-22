<?php

use App\Http\Controllers\API\AuditLogController;
use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\JobsController;
use App\Http\Controllers\API\LnmpController;
use App\Http\Controllers\API\MetricsController;
use App\Http\Controllers\API\PermissionController;
use App\Http\Controllers\API\ProcessController;
use App\Http\Controllers\API\RoleController;
use App\Http\Controllers\API\SystemServicesController;
use App\Http\Controllers\API\UserController;
use Illuminate\Support\Facades\Route;

Route::controller(AuthController::class)->group(function () {
    Route::post('auth/login', 'login');

    Route::middleware('auth:sanctum')->group(function () {
        Route::prefix('/auth')->name('auth.')->group(function () {
            Route::get('data', 'data')->name('data');
            Route::delete('logout', 'logout')->name('logout');
        });

        // Route pour changer le mot de passe (accessible même si password_change_required est true)
        Route::put('users/update-password', [UserController::class, 'updatePassword'])->name('user.update-password');

        // Routes protégées par le middleware de statut de compte
        Route::middleware('account.status')->group(function () {
            // Routes utilisateurs
            Route::prefix('users')->name('user.')->controller(UserController::class)->group(function () {
                Route::get('/', 'index')->name('index');
                Route::post('/', 'store')->name('store');
                Route::get('/{id}', 'show')->name('show');
                Route::put('/{id}', 'update')->name('update');
                Route::delete('/{id}', 'destroy')->name('destroy');
                Route::put('/{id}/roles', 'syncRoles')
                    ->middleware(['audit:user.sync-roles', 'permission:manage,role'])
                    ->name('sync-roles');
            });

            // Routes des rôles (RBAC dynamique)
            Route::prefix('roles')->name('role.')->controller(RoleController::class)->group(function () {
                Route::get('/', 'index')->name('index');
                Route::post('/', 'store')->name('store');
                Route::get('/{id}', 'show')->name('show');
                Route::put('/{id}', 'update')->name('update');
                Route::delete('/{id}', 'destroy')->name('destroy');
            });

            // Routes des permissions (RBAC dynamique)
            Route::prefix('permissions')->name('permission.')->controller(PermissionController::class)->group(function () {
                Route::get('/', 'index')->name('index');
                Route::post('/', 'store')->name('store');
                Route::get('/{id}', 'show')->name('show');
                Route::put('/{id}', 'update')->name('update');
                Route::delete('/{id}', 'destroy')->name('destroy');
            });

            // Monitoring système — métriques temps réel (polling dashboard)
            Route::get('metrics', [MetricsController::class, 'index'])
                ->middleware('permission:read,system')
                ->name('metrics.index');

            // Gestionnaire de processus
            Route::prefix('processes')->name('process.')->controller(ProcessController::class)->group(function () {
                Route::get('/', 'index')->middleware('permission:read,process')->name('index');
                // `audit` avant `permission` : les refus 403 sont ainsi tracés.
                Route::post('/kill', 'kill')
                    ->middleware(['audit:process.kill', 'permission:manage,system'])
                    ->name('kill');
            });

            // Services LNMP (nginx, MySQL, PHP-FPM)
            Route::prefix('lnmp')->name('lnmp.')->controller(LnmpController::class)->group(function () {
                Route::get('/', 'index')->middleware('permission:read,service')->name('index');
                Route::post('/action', 'action')
                    ->middleware(['audit:service.action', 'permission:manage,system'])
                    ->name('action');
            });

            // Tous les services systemd (lecture seule)
            Route::get('system-services', [SystemServicesController::class, 'index'])
                ->middleware('permission:read,service')
                ->name('system-services.index');

            // File de jobs Laravel
            Route::prefix('jobs')->name('jobs.')->controller(JobsController::class)->group(function () {
                Route::get('/', 'index')->middleware('permission:read,system')->name('index');
                Route::post('/{uuid}/retry', 'retry')
                    ->middleware(['audit:job.retry', 'permission:manage,system'])
                    ->name('retry');
            });

            // Audit trail (lecture seule)
            Route::prefix('audit-logs')->name('audit-log.')->controller(AuditLogController::class)->group(function () {
                Route::get('/', 'index')->name('index');
                Route::get('/{id}', 'show')->name('show');
            });
        });
    });
});
