<?php
// ══════════════════════════════════════════════════════════════════════════
// database/seeders/DriverSeeder.php
// ══════════════════════════════════════════════════════════════════════════
namespace Database\Seeders;

use App\Enums\Role;
use App\Models\Driver;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DriverSeeder extends Seeder
{
    public function run(): void
    {
        $drivers = [
            [
                'employee_number'    => 'CH-2022-001',
                'first_name'         => 'Koné',
                'last_name'          => 'Aboubakar',
                'phone'              => '+225 07 01 23 45 67',
                'license_number'     => 'CI-D-2019-00142',
                'license_category'   => 'D',
                'license_expires_at' => '2026-08-15',
                'medical_expires_at' => '2025-11-30',
                'hired_at'           => '2022-03-01',
                'status'             => 'available',
            ],
            [
                'employee_number'    => 'CH-2022-002',
                'first_name'         => 'Traoré',
                'last_name'          => 'Karim',
                'phone'              => '+225 05 44 67 89 12',
                'license_number'     => 'CI-D-2018-00087',
                'license_category'   => 'D',
                'license_expires_at' => '2026-03-22',
                'medical_expires_at' => '2026-02-28',
                'hired_at'           => '2022-06-15',
                'status'             => 'available',
            ],
            [
                'employee_number'    => 'CH-2021-003',
                'first_name'         => 'Coulibaly',
                'last_name'          => 'Moussa',
                'phone'              => '+225 01 55 32 10 44',
                'license_number'     => 'CI-D-2016-00231',
                'license_category'   => 'D+E',
                'license_expires_at' => '2027-01-10',
                'medical_expires_at' => '2026-06-15',
                'hired_at'           => '2021-01-10',
                'status'             => 'available',
            ],
            [
                'employee_number'    => 'CH-2023-004',
                'first_name'         => 'Diallo',
                'last_name'          => 'Boubacar',
                'phone'              => '+225 07 88 91 23 56',
                'license_number'     => 'CI-D-2020-00318',
                'license_category'   => 'D',
                'license_expires_at' => '2025-06-30', // expire bientôt!
                'medical_expires_at' => '2025-09-15',
                'hired_at'           => '2023-04-20',
                'status'             => 'available',
            ],
            [
                'employee_number'    => 'CH-2020-005',
                'first_name'         => 'Bamba',
                'last_name'          => 'Drissa',
                'phone'              => '+225 05 12 34 56 78',
                'license_number'     => 'CI-D-2015-00055',
                'license_category'   => 'D+E',
                'license_expires_at' => '2027-11-20',
                'medical_expires_at' => '2026-04-30',
                'hired_at'           => '2020-09-01',
                'status'             => 'available',
            ],
            [
                'employee_number'    => 'CH-2023-006',
                'first_name'         => 'Ouattara',
                'last_name'          => 'Fanta',
                'phone'              => '+225 07 65 43 21 09',
                'license_number'     => 'CI-D-2021-00402',
                'license_category'   => 'D',
                'license_expires_at' => '2026-09-05',
                'medical_expires_at' => '2026-09-05',
                'hired_at'           => '2023-07-01',
                'status'             => 'available',
            ],
            [
                'employee_number'    => 'CH-2019-007',
                'first_name'         => 'Yao',
                'last_name'          => 'Kouassi',
                'phone'              => '+225 01 98 76 54 32',
                'license_number'     => 'CI-D-2014-00019',
                'license_category'   => 'D+E',
                'license_expires_at' => '2026-12-31',
                'medical_expires_at' => '2025-12-31',
                'hired_at'           => '2019-11-15',
                'status'             => 'available',
            ],
            [
                'employee_number'    => 'CH-2024-008',
                'first_name'         => 'N\'Guessan',
                'last_name'          => 'Arsène',
                'phone'              => '+225 07 22 33 44 55',
                'license_number'     => 'CI-D-2022-00511',
                'license_category'   => 'D',
                'license_expires_at' => '2028-03-15',
                'medical_expires_at' => '2026-03-15',
                'hired_at'           => '2024-01-08',
                'status'             => 'on_leave',
                
            ],
        ];

        foreach ($drivers as $driver) {
            $driverModel = Driver::firstOrCreate(
                ['employee_number' => $driver['employee_number']],
                $driver
            );

            // Compte de connexion du chauffeur — email dérivé du matricule, mot de
            // passe 'password' comme les autres comptes de test.
            $email = strtolower($driverModel->employee_number) . '@tms-ci.com';
            $user = User::firstOrCreate(
                ['email' => $email],
                [
                    'name'     => "{$driverModel->first_name} {$driverModel->last_name}",
                    'password' => Hash::make('password'),
                    'role'     => Role::DRIVER,
                ]
            );
            if ($driverModel->user_id !== $user->id) {
                $driverModel->update(['user_id' => $user->id]);
            }
        }

        $this->command->info('  Chauffeurs créés: ' . count($drivers));
    }
}
