<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

/**
 * Seeder initial du système RBAC.
 *
 * Crée :
 * - un rôle "admin" super-administrateur (tous les droits),
 * - un jeu de permissions de base (utilisateurs, rôles, permissions).
 *
 * Adaptez librement à votre domaine. Lancez-le via :
 *   php artisan db:seed --class=RolePermissionSeeder
 */
class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        // Rôle super-administrateur : accorde automatiquement « manage / all ».
        Role::firstOrCreate(
            ['name' => 'admin'],
            [
                'label' => 'Administrateur',
                'description' => 'Accès complet à toutes les ressources',
                'is_super_admin' => true,
            ]
        );

        // Rôle observateur : lecture seule (dashboard, processus, services, logs).
        $observer = Role::firstOrCreate(
            ['name' => 'observer'],
            [
                'label' => 'Observateur',
                'description' => 'Accès en lecture seule aux métriques et au monitoring',
                'is_super_admin' => false,
            ]
        );

        // Permissions de base. Ajoutez-en autant que nécessaire à l'avenir.
        $permissions = [
            ['action' => 'read', 'subject' => 'user', 'label' => 'Voir les utilisateurs'],
            ['action' => 'create', 'subject' => 'user', 'label' => 'Créer un utilisateur'],
            ['action' => 'update', 'subject' => 'user', 'label' => 'Modifier un utilisateur'],
            ['action' => 'delete', 'subject' => 'user', 'label' => 'Supprimer un utilisateur'],
            ['action' => 'manage', 'subject' => 'role', 'label' => 'Gérer les rôles'],
            ['action' => 'manage', 'subject' => 'permission', 'label' => 'Gérer les permissions'],
            // Monitoring système (MawenaPulse)
            ['action' => 'read', 'subject' => 'system', 'label' => 'Voir les métriques système'],
            ['action' => 'manage', 'subject' => 'system', 'label' => 'Actions critiques système (kill, restart…)'],
            ['action' => 'read', 'subject' => 'process', 'label' => 'Voir les processus'],
            ['action' => 'read', 'subject' => 'service', 'label' => 'Voir les services LNMP'],
            ['action' => 'read', 'subject' => 'audit-log', 'label' => "Consulter l'audit trail"],
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(
                ['action' => $permission['action'], 'subject' => $permission['subject']],
                ['label' => $permission['label'] ?? null]
            );
        }

        // L'observateur reçoit uniquement les permissions de lecture monitoring.
        $readOnly = Permission::query()
            ->where('action', 'read')
            ->whereIn('subject', ['system', 'process', 'service'])
            ->pluck('id');
        $observer->permissions()->syncWithoutDetaching($readOnly);
    }
}
