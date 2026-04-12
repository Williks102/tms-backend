<?php
// ══════════════════════════════════════════════════════════════════════════
// database/seeders/DatabaseSeeder.php
// Lancer avec: php artisan db:seed
// Ou tout réinitialiser: php artisan migrate:fresh --seed
// ══════════════════════════════════════════════════════════════════════════
namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            UserSeeder::class,           // 1. Utilisateurs admin + gestionnaires
            BoardingGateSeeder::class,   // 2. Quais d'embarquement
            RouteSeeder::class,          // 3. Lignes de transport
            VehicleSeeder::class,        // 4. Parc de véhicules
            DriverSeeder::class,         // 5. Chauffeurs
            ScheduleTemplateSeeder::class, // 6. Gabarits horaires
            DepartureSeeder::class,      // 7. Départs (7 derniers jours)
            FuelSeeder::class,           // 8. Bons carburant + consommations
            IncidentSeeder::class,       // 9. Incidents
        ]);

        $this->command->info('✅ TMS seedé avec succès — données Côte d\'Ivoire');
    }
}
