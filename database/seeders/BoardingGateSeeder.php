<?php
// ══════════════════════════════════════════════════════════════════════════
// database/seeders/BoardingGateSeeder.php
// ══════════════════════════════════════════════════════════════════════════
namespace Database\Seeders;

use App\Models\BoardingGate;
use Illuminate\Database\Seeder;

class BoardingGateSeeder extends Seeder
{
    public function run(): void
    {
        $gates = [
            // Gare d'Adjamé (Abidjan)
            ['station_name' => 'Gare d\'Adjamé', 'gate_code' => 'Q1', 'is_active' => true],
            ['station_name' => 'Gare d\'Adjamé', 'gate_code' => 'Q2', 'is_active' => true],
            ['station_name' => 'Gare d\'Adjamé', 'gate_code' => 'Q3', 'is_active' => true],
            ['station_name' => 'Gare d\'Adjamé', 'gate_code' => 'Q4', 'is_active' => true],
            ['station_name' => 'Gare d\'Adjamé', 'gate_code' => 'Q5', 'is_active' => false],

            // Gare de Yopougon
            ['station_name' => 'Gare de Yopougon', 'gate_code' => 'Q1', 'is_active' => true],
            ['station_name' => 'Gare de Yopougon', 'gate_code' => 'Q2', 'is_active' => true],
            ['station_name' => 'Gare de Yopougon', 'gate_code' => 'Q3', 'is_active' => true],
        ];

        foreach ($gates as $gate) {
            BoardingGate::firstOrCreate(
                ['station_name' => $gate['station_name'], 'gate_code' => $gate['gate_code']],
                $gate
            );
        }

        $this->command->info('  Quais créés: ' . count($gates));
    }
}