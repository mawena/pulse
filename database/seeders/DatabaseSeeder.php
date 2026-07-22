<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Rôles & permissions RBAC (admin, observer, permissions système).
        $this->call(RolePermissionSeeder::class);

        // Compte administrateur par défaut (mot de passe à changer à la 1ère connexion).
        $admin = User::firstOrCreate(
            ['email' => 'admin@mawena.cloud'],
            [
                'name' => 'Admin',
                'password' => 'password',
                'activated' => true,
                'password_change_required' => true,
            ]
        );
        $admin->assignRole('admin');
    }
}
