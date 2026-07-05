<?php
// ══════════════════════════════════════════════════════════════════════════
// database/seeders/DepartureSeeder.php
// Crée des départs réalistes sur les 7 derniers jours + aujourd'hui
// ══════════════════════════════════════════════════════════════════════════
namespace Database\Seeders;

use App\Models\BoardingGate;
use App\Models\Departure;
use App\Models\Driver;
use App\Models\Route;
use App\Models\Vehicle;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class DepartureSeeder extends Seeder
{
    public function run(): void
    {
        $vehicles = Vehicle::where('status', '!=', 'inactive')->get();
        $drivers  = Driver::where('status', '!=', 'suspended')->where('status', '!=', 'on_leave')->get();
        $routes   = Route::active()->get();
        $gates    = BoardingGate::whereHas('station', fn($q) => $q->where('name', "Gare d'Adjamé"))
            ->where('is_active', true)->pluck('id')->all();

        $vehicleIdx = 0;
        $driverIdx  = 0;
        $count      = 0;

        // Départs des 6 derniers jours (tous arrivés)
        for ($daysAgo = 6; $daysAgo >= 1; $daysAgo--) {
            $date = now()->subDays($daysAgo);

            // 3-4 départs par jour passé
            $dailyRoutes = $routes->random(min(4, $routes->count()));

            foreach ($dailyRoutes as $route) {
                $vehicle = $vehicles[$vehicleIdx % $vehicles->count()];
                $driver  = $drivers[$driverIdx % $drivers->count()];
                $vehicleIdx++;
                $driverIdx++;

                $departureAt    = $date->copy()->setHour(rand(5, 14))->setMinute(0)->setSecond(0);
                $estimatedArrival = $departureAt->copy()->addMinutes($route->estimated_duration_min);

                // Retard aléatoire réaliste (0-25 min)
                $delayMin      = rand(0, 25);
                $actualDepart  = $departureAt->copy()->addMinutes(rand(0, 10));
                $actualArrival = $estimatedArrival->copy()->addMinutes($delayMin);

                Departure::create([
                    'route_id'           => $route->id,
                    'vehicle_id'         => $vehicle->id,
                    'driver_id'          => $driver->id,
                    'departure_datetime' => $departureAt,
                    'estimated_arrival'  => $estimatedArrival,
                    'actual_departure'   => $actualDepart,
                    'actual_arrival'     => $actualArrival,
                    'boarding_gate_id'   => $gates[array_rand($gates)],
                    'seats_available'    => $vehicle->capacity,
                    'status'             => 'arrived',
                ]);
                $count++;
            }
        }

        // Départs d'aujourd'hui — statuts variés
        $todayDepartures = [
            [
                'route_code' => 'ABJ-BKE',
                'hour'       => 6,
                'status'     => 'arrived',
                'delay'      => 8,
            ],
            [
                'route_code' => 'ABJ-YAM',
                'hour'       => 7,
                'status'     => 'departed',
                'delay'      => 5,
            ],
            [
                'route_code' => 'ABJ-SAN',
                'hour'       => 8,
                'status'     => 'boarding',
                'delay'      => 0,
            ],
            [
                'route_code' => 'BKE-ABJ',
                'hour'       => 5,
                'status'     => 'arrived',
                'delay'      => 12,
            ],
            [
                'route_code' => 'ABJ-DAL',
                'hour'       => 10,
                'status'     => 'scheduled',
                'delay'      => 0,
            ],
            [
                'route_code' => 'ABJ-MAN',
                'hour'       => 14,
                'status'     => 'scheduled',
                'delay'      => 0,
            ],
        ];

        foreach ($todayDepartures as $i => $dep) {
            $route    = Route::where('code', $dep['route_code'])->first();
            $vehicle  = $vehicles[$i % $vehicles->count()];
            $driver   = $drivers[$i % $drivers->count()];

            if (!$route) continue;

            $departureAt      = now()->startOfDay()->addHours($dep['hour']);
            $estimatedArrival = $departureAt->copy()->addMinutes($route->estimated_duration_min);

            $data = [
                'route_id'           => $route->id,
                'vehicle_id'         => $vehicle->id,
                'driver_id'          => $driver->id,
                'departure_datetime' => $departureAt,
                'estimated_arrival'  => $estimatedArrival,
                'boarding_gate_id'   => $gates[$i % count($gates)],
                'seats_available'    => $vehicle->capacity,
                'status'             => $dep['status'],
            ];

            // Données temporelles selon statut
            if (in_array($dep['status'], ['departed', 'arrived'])) {
                $data['actual_departure'] = $departureAt->copy()->addMinutes(rand(0, 8));
            }
            if ($dep['status'] === 'arrived') {
                $data['actual_arrival'] = $estimatedArrival->copy()->addMinutes($dep['delay']);
            }

            Departure::create($data);
            $count++;
        }

        $this->command->info("  Départs créés: {$count}");
    }
}
