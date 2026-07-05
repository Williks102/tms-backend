<?php
// ══════════════════════════════════════════════════════════════════════════
// database/seeders/BoardingGateSeeder.php
// ══════════════════════════════════════════════════════════════════════════
namespace Database\Seeders;

use App\Models\BoardingGate;
use App\Models\Station;
use Illuminate\Database\Seeder;

class BoardingGateSeeder extends Seeder
{
    public function run(): void
    {
        $stations = [
            "Gare d'Adjamé"    => 'Abidjan',
            'Gare de Yopougon' => 'Abidjan',
        ];

        $stationIds = [];
        foreach ($stations as $name => $city) {
            $station = Station::firstOrCreate(['name' => $name], ['city' => $city, 'is_active' => true]);
            $stationIds[$name] = $station->id;
        }

        $gates = [
            // Gare d'Adjamé (Abidjan)
            ['station' => "Gare d'Adjamé", 'gate_code' => 'Q1', 'is_active' => true],
            ['station' => "Gare d'Adjamé", 'gate_code' => 'Q2', 'is_active' => true],
            ['station' => "Gare d'Adjamé", 'gate_code' => 'Q3', 'is_active' => true],
            ['station' => "Gare d'Adjamé", 'gate_code' => 'Q4', 'is_active' => true],
            ['station' => "Gare d'Adjamé", 'gate_code' => 'Q5', 'is_active' => false],

            // Gare de Yopougon
            ['station' => 'Gare de Yopougon', 'gate_code' => 'Q1', 'is_active' => true],
            ['station' => 'Gare de Yopougon', 'gate_code' => 'Q2', 'is_active' => true],
            ['station' => 'Gare de Yopougon', 'gate_code' => 'Q3', 'is_active' => true],
        ];

        foreach ($gates as $gate) {
            BoardingGate::firstOrCreate(
                ['station_id' => $stationIds[$gate['station']], 'gate_code' => $gate['gate_code']],
                ['station_id' => $stationIds[$gate['station']], 'gate_code' => $gate['gate_code'], 'is_active' => $gate['is_active']]
            );
        }

        $this->command->info('  Gares créées: ' . count($stations) . ' — Quais créés: ' . count($gates));
    }
}
