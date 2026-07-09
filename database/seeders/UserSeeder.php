<?php
// ══════════════════════════════════════════════════════════════════════════
// database/seeders/UserSeeder.php
// ══════════════════════════════════════════════════════════════════════════
namespace Database\Seeders;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $users = [
            [
                'name'     => 'Directeur Général',
                'email'    => 'dg@tms-ci.com',
                'password' => Hash::make('password'),
                'role'     => Role::DG,
            ],
            [
                'name'     => 'Koné Aboubakar',
                'email'    => 'manager@tms-ci.com',
                'password' => Hash::make('password'),
                'role'     => Role::MANAGER,
            ],
            [
                'name'     => 'Aka Brice',
                'email'    => 'dispatcher@tms-ci.com',
                'password' => Hash::make('password'),
                'role'     => Role::DISPATCHER,
            ],
            [
                'name'     => 'Touré Fatoumata',
                'email'    => 'rh@tms-ci.com',
                'password' => Hash::make('password'),
                'role'     => Role::RH,
            ],
            [
                'name'     => 'Yao Stéphanie',
                'email'    => 'caissier@tms-ci.com',
                'password' => Hash::make('password'),
                'role'     => Role::CAISSIER,
            ],
            [
                'name'     => 'Kouadio Serge',
                'email'    => 'controleur@tms-ci.com',
                'password' => Hash::make('password'),
                'role'     => Role::CONTROLEUR,
            ],
            [
                'name'     => 'Diabaté Awa',
                'email'    => 'comptable@tms-ci.com',
                'password' => Hash::make('password'),
                'role'     => Role::COMPTABLE,
            ],
            [
                'name'     => 'Ouattara Ibrahim',
                'email'    => 'colis@tms-ci.com',
                'password' => Hash::make('password'),
                'role'     => Role::AGENT_COLIS,
            ],
        ];

        foreach ($users as $user) {
            User::updateOrCreate(['email' => $user['email']], $user);
        }

        $this->command->info('  Users créés: ' . count($users));
    }
}




