<?php
// ══════════════════════════════════════════════════════════════════════════
// database/seeders/UserSeeder.php
// ══════════════════════════════════════════════════════════════════════════
namespace Database\Seeders;

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
            ],
            [
                'name'     => 'Koné Aboubakar',
                'email'    => 'manager@tms-ci.com',
                'password' => Hash::make('password'),
            ],
            [
                'name'     => 'Aka Brice',
                'email'    => 'dispatcher@tms-ci.com',
                'password' => Hash::make('password'),
            ],
            [
                'name'     => 'Touré Fatoumata',
                'email'    => 'rh@tms-ci.com',
                'password' => Hash::make('password'),
            ],
        ];

        foreach ($users as $user) {
            User::firstOrCreate(['email' => $user['email']], $user);
        }

        $this->command->info('  Users créés: ' . count($users));
    }
}




