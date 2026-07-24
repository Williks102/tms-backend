<?php
// ══════════════════════════════════════════════════════════════════════════
// database/seeders/VehicleSeeder.php
// ══════════════════════════════════════════════════════════════════════════
namespace Database\Seeders;

use App\Models\Vehicle;
use Illuminate\Database\Seeder;

class VehicleSeeder extends Seeder
{
    public function run(): void
    {
        $vehicles = [
            [
                'plate_number'                => 'CI-4821-AB',
                'model'                       => 'Mercedes-Benz Tourismo',
                'capacity'                    => 49,
                'fuel_consumption_per_100km'  => 28.5,
                'current_mileage_km'          => 187420,
                'last_maintenance_km'         => 180000,
                'maintenance_interval_km'     => 10000,
                'status'                      => 'available',
            ],
            [
                'plate_number'                => 'CI-3302-CD',
                'model'                       => 'Yutong ZK6122H9',
                'capacity'                    => 53,
                'fuel_consumption_per_100km'  => 31.0,
                'current_mileage_km'          => 204800,
                'last_maintenance_km'         => 195000,
                'maintenance_interval_km'     => 10000,
                'status'                      => 'available',
                'notes'                       => 'Vidange due — prévoir intervention',
            ],
            [
                'plate_number'                => 'CI-7741-EF',
                'model'                       => 'King Long XMQ6127',
                'capacity'                    => 55,
                'fuel_consumption_per_100km'  => 29.0,
                'current_mileage_km'          => 143200,
                'last_maintenance_km'         => 140000,
                'maintenance_interval_km'     => 10000,
                'status'                      => 'available',
            ],
            [
                'plate_number'                => 'CI-5512-GH',
                'model'                       => 'Higer KLQ6129Q',
                'capacity'                    => 51,
                'fuel_consumption_per_100km'  => 27.5,
                'current_mileage_km'          => 98500,
                'last_maintenance_km'         => 90000,
                'maintenance_interval_km'     => 10000,
                'status'                      => 'available',
            ],
            [
                'plate_number'                => 'CI-2290-IJ',
                'model'                       => 'Mercedes-Benz O500',
                'capacity'                    => 46,
                'fuel_consumption_per_100km'  => 32.0,
                'current_mileage_km'          => 312000,
                'last_maintenance_km'         => 310000,
                'maintenance_interval_km'     => 10000,
                'status'                      => 'available',
            ],
            [
                'plate_number'                => 'CI-8830-KL',
                'model'                       => 'Yutong ZK6119H',
                'capacity'                    => 49,
                'fuel_consumption_per_100km'  => 30.0,
                'current_mileage_km'          => 67800,
                'last_maintenance_km'         => 60000,
                'maintenance_interval_km'     => 10000,
                'status'                      => 'available',
            ],
            [
                'plate_number'                => 'CI-1104-MN',
                'model'                       => 'Scania Touring HD',
                'capacity'                    => 48,
                'fuel_consumption_per_100km'  => 26.5,
                'current_mileage_km'          => 45200,
                'last_maintenance_km'         => 40000,
                'maintenance_interval_km'     => 10000,
                'status'                      => 'maintenance',
                'notes'                       => 'Révision freins en cours',
            ],
            [
                'plate_number'                => 'CI-6673-OP',
                'model'                       => 'King Long XMQ6900',
                'capacity'                    => 35,
                'fuel_consumption_per_100km'  => 22.0,
                'current_mileage_km'          => 156700,
                'last_maintenance_km'         => 150000,
                'maintenance_interval_km'     => 10000,
                'status'                      => 'available',
                'notes'                       => 'Véhicule courtes distances',
            ],
            // ── Camions (module Fret) ────────────────────────────────────
            [
                'plate_number'                => 'CI-9012-QR',
                'type'                        => 'truck',
                'model'                       => 'Mercedes-Benz Actros',
                'capacity'                    => 0,
                'cargo_capacity_kg'           => 20000,
                'fuel_consumption_per_100km'  => 35.0,
                'current_mileage_km'          => 88200,
                'last_maintenance_km'         => 80000,
                'maintenance_interval_km'     => 15000,
                'status'                      => 'available',
            ],
            [
                'plate_number'                => 'CI-4456-ST',
                'type'                        => 'truck',
                'model'                       => 'Renault Kerax',
                'capacity'                    => 0,
                'cargo_capacity_kg'           => 15000,
                'fuel_consumption_per_100km'  => 33.0,
                'current_mileage_km'          => 132400,
                'last_maintenance_km'         => 130000,
                'maintenance_interval_km'     => 15000,
                'status'                      => 'available',
            ],
        ];

        foreach ($vehicles as $vehicle) {
            Vehicle::firstOrCreate(
                ['plate_number' => $vehicle['plate_number']],
                $vehicle
            );
        }

        $this->command->info('  Véhicules créés: ' . count($vehicles));
    }
}
