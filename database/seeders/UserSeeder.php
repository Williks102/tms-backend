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
        // Comptes démo (mot de passe 'password' en clair, affichés sur /login)
        // — jamais en production, ni via migrate:fresh --seed ni via db:seed
        // isolé (runbook sécurité v3, point 5).
        if (app()->environment('production')) {
            $this->command->warn('  UserSeeder ignoré en production (comptes démo).');
            return;
        }

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
            [
                'name'     => 'Bamba Souleymane',
                'email'    => 'fret@tms-ci.com',
                'password' => Hash::make('password'),
                'role'     => Role::AGENT_FRET,
            ],
        ];

        foreach ($users as $user) {
            User::updateOrCreate(['email' => $user['email']], $user);
        }

        $this->command->info('  Users créés: ' . count($users));
    }
}




