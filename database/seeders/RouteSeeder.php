<?php
// ══════════════════════════════════════════════════════════════════════════
// database/seeders/RouteSeeder.php
// ══════════════════════════════════════════════════════════════════════════
namespace Database\Seeders;

use App\Models\Route;
use App\Models\RouteStop;
use App\Models\Station;
use Illuminate\Database\Seeder;

class RouteSeeder extends Seeder
{
    public function run(): void
    {
        $adjameId = Station::where('name', "Gare d'Adjamé")->value('id');

        $routes = [
            [
                'code'                   => 'ABJ-BKE',
                'name'                   => 'Abidjan → Bouaké',
                'origin_city'            => 'Abidjan',
                'destination_city'       => 'Bouaké',
                'distance_km'            => 380,
                'estimated_duration_min' => 240, // 4h
                'is_dynamic'             => false,
                'base_fare'              => 5000,
                'is_active'              => true,
            ],
            [
                'code'                   => 'ABJ-YAM',
                'name'                   => 'Abidjan → Yamoussoukro',
                'origin_city'            => 'Abidjan',
                'destination_city'       => 'Yamoussoukro',
                'distance_km'            => 248,
                'estimated_duration_min' => 150, // 2h30
                'is_dynamic'             => false,
                'base_fare'              => 3500,
                'is_active'              => true,
            ],
            [
                'code'                   => 'ABJ-SAN',
                'name'                   => 'Abidjan → San-Pédro',
                'origin_city'            => 'Abidjan',
                'destination_city'       => 'San-Pédro',
                'distance_km'            => 340,
                'estimated_duration_min' => 210, // 3h30
                'is_dynamic'             => false,
                'base_fare'              => 4500,
                'is_active'              => true,
            ],
            [
                'code'                   => 'ABJ-DAL',
                'name'                   => 'Abidjan → Daloa',
                'origin_city'            => 'Abidjan',
                'destination_city'       => 'Daloa',
                'distance_km'            => 410,
                'estimated_duration_min' => 270, // 4h30
                'is_dynamic'             => true,  // Arrêts intermédiaires
                'base_fare'              => 5500,
                'is_active'              => true,
            ],
            [
                'code'                   => 'ABJ-MAN',
                'name'                   => 'Abidjan → Man',
                'origin_city'            => 'Abidjan',
                'destination_city'       => 'Man',
                'distance_km'            => 610,
                'estimated_duration_min' => 420, // 7h
                'is_dynamic'             => true,
                'base_fare'              => 8000,
                'is_active'              => true,
            ],
            [
                'code'                   => 'BKE-ABJ',
                'name'                   => 'Bouaké → Abidjan',
                'origin_city'            => 'Bouaké',
                'destination_city'       => 'Abidjan',
                'distance_km'            => 380,
                'estimated_duration_min' => 240,
                'is_dynamic'             => false,
                'base_fare'              => 5000,
                'is_active'              => true,
            ],
            [
                'code'                   => 'ABJ-KOR',
                'name'                   => 'Abidjan → Korhogo',
                'origin_city'            => 'Abidjan',
                'destination_city'       => 'Korhogo',
                'distance_km'            => 632,
                'estimated_duration_min' => 480, // 8h
                'is_dynamic'             => false,
                'base_fare'              => 9000,
                'is_active'              => true,
            ],
        ];

        foreach ($routes as $routeData) {
            if ($routeData['origin_city'] === 'Abidjan') {
                $routeData['origin_station_id'] = $adjameId;
            }

            $route = Route::firstOrCreate(
                ['code' => $routeData['code']],
                $routeData
            );

            // Arrêts pour les lignes dynamiques
            if ($route->is_dynamic && $route->stops()->count() === 0) {
                $this->addStops($route);
            }
        }

        $this->command->info('  Lignes créées: ' . count($routes));
    }

    private function addStops(Route $route): void
    {
        $stops = match ($route->code) {
            'ABJ-DAL' => [
                ['city_name' => 'Fresco',    'stop_order' => 1, 'distance_from_origin_km' => 180, 'fare_from_origin' => 2500],
                ['city_name' => 'Lakota',    'stop_order' => 2, 'distance_from_origin_km' => 280, 'fare_from_origin' => 3500],
                ['city_name' => 'Gagnoa',    'stop_order' => 3, 'distance_from_origin_km' => 340, 'fare_from_origin' => 4500],
            ],
            'ABJ-MAN' => [
                ['city_name' => 'Yamoussoukro', 'stop_order' => 1, 'distance_from_origin_km' => 248, 'fare_from_origin' => 3500],
                ['city_name' => 'Daloa',        'stop_order' => 2, 'distance_from_origin_km' => 410, 'fare_from_origin' => 5500],
                ['city_name' => 'Duékoué',      'stop_order' => 3, 'distance_from_origin_km' => 530, 'fare_from_origin' => 7000],
            ],
            default => [],
        };

        foreach ($stops as $stop) {
            RouteStop::firstOrCreate(
                ['route_id' => $route->id, 'city_name' => $stop['city_name']],
                array_merge($stop, ['route_id' => $route->id])
            );
        }
    }
}
