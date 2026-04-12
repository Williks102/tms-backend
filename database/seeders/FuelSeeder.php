<?php
// ══════════════════════════════════════════════════════════════════════════
// database/seeders/FuelSeeder.php
// ══════════════════════════════════════════════════════════════════════════
namespace Database\Seeders;

use App\Models\Departure;
use App\Models\FuelConsumptionLog;
use App\Models\FuelVoucher;
use App\Models\User;
use Illuminate\Database\Seeder;

class FuelSeeder extends Seeder
{
    public function run(): void
    {
        $manager    = User::where('email', 'manager@tms-ci.com')->first();
        $dispatcher = User::where('email', 'dispatcher@tms-ci.com')->first();

        // Récupère les départs arrivés des 6 derniers jours
        $arrivedDepartures = Departure::where('status', 'arrived')
            ->with(['vehicle', 'driver', 'route'])
            ->latest('departure_datetime')
            ->limit(20)
            ->get();

        $pricePerLiter = 720; // FCFA — prix gasoil Côte d'Ivoire
        $voucherCount  = 0;
        $logCount      = 0;

        foreach ($arrivedDepartures as $departure) {
            $route   = $departure->route;
            $vehicle = $departure->vehicle;

            // Consommation théorique
            $theoretical = ($route->distance_km * $vehicle->fuel_consumption_per_100km) / 100;

            // Simulation: 80% des départs ont un bon normal, 15% légèrement surconsommé, 5% anomalie
            $rand = rand(1, 100);
            if ($rand <= 80) {
                $requestedLiters = round($theoretical * (1 + (rand(-5, 8) / 100)), 1); // ±5-8%
                $status = 'consumed';
            } elseif ($rand <= 95) {
                $requestedLiters = round($theoretical * (1 + (rand(8, 14) / 100)), 1); // +8-14%
                $status = 'consumed';
            } else {
                $requestedLiters = round($theoretical * (1 + (rand(16, 30) / 100)), 1); // +16-30% → anomalie
                $status = 'approved'; // En attente investigation
            }

            $approvedLiters = $status === 'consumed' ? $requestedLiters : null;
            $totalCost      = $approvedLiters ? round($approvedLiters * $pricePerLiter) : null;

            $voucher = FuelVoucher::create([
                'departure_id'       => $departure->id,
                'driver_id'          => $departure->driver_id,
                'vehicle_id'         => $departure->vehicle_id,
                'requested_liters'   => $requestedLiters,
                'theoretical_liters' => round($theoretical, 1),
                'approved_liters'    => $approvedLiters,
                'fuel_type'          => 'gasoil',
                'price_per_liter'    => $pricePerLiter,
                'total_cost'         => $totalCost,
                'station_name'       => $this->randomStation($route->origin_city),
                'status'             => $status,
                'requested_at'       => $departure->departure_datetime->subMinutes(30),
                'approved_at'        => $status !== 'pending' ? $departure->departure_datetime : null,
                'approved_by'        => $status !== 'pending' ? $manager->id : null,
            ]);
            $voucherCount++;

            // Log de consommation réelle pour les bons consumed
            if ($status === 'consumed') {
                $actualLiters = round($requestedLiters * (1 + (rand(-3, 3) / 100)), 1);

                FuelConsumptionLog::create([
                    'vehicle_id'            => $departure->vehicle_id,
                    'departure_id'          => $departure->id,
                    'fuel_voucher_id'       => $voucher->id,
                    'liters_consumed'       => $actualLiters,
                    'distance_km'           => $route->distance_km,
                    'consumption_per_100km' => round(($actualLiters / $route->distance_km) * 100, 2),
                    'mileage_before'        => $vehicle->current_mileage_km - $route->distance_km,
                    'mileage_after'         => $vehicle->current_mileage_km,
                    'recorded_at'           => $departure->actual_arrival ?? $departure->estimated_arrival,
                    'recorded_by'           => $dispatcher->id,
                ]);
                $logCount++;
            }
        }

        // Quelques bons en attente de validation (départs récents)
        $pendingDepartures = Departure::where('status', 'arrived')
            ->latest()->limit(3)->get();

        foreach ($pendingDepartures as $dep) {
            $theoretical = ($dep->route->distance_km * $dep->vehicle->fuel_consumption_per_100km) / 100;
            FuelVoucher::create([
                'departure_id'       => $dep->id,
                'driver_id'          => $dep->driver_id,
                'vehicle_id'         => $dep->vehicle_id,
                'requested_liters'   => round($theoretical * 1.05, 1),
                'theoretical_liters' => round($theoretical, 1),
                'fuel_type'          => 'gasoil',
                'price_per_liter'    => $pricePerLiter,
                'station_name'       => $this->randomStation($dep->route->origin_city ?? 'Abidjan'),
                'status'             => 'pending',
                'requested_at'       => now()->subHours(rand(1, 5)),
            ]);
            $voucherCount++;
        }

        $this->command->info("  Bons carburant créés: {$voucherCount} | Logs consommation: {$logCount}");
    }

    private function randomStation(string $city): string
    {
        $stations = [
            'Abidjan'       => ['Total Adjamé', 'Shell Plateau', 'Vivo Yopougon', 'Total Cocody'],
            'Bouaké'        => ['Total Bouaké Centre', 'Shell Bouaké Nord'],
            'Yamoussoukro'  => ['Vivo Yamoussoukro', 'Total Yamoussoukro'],
            'San-Pédro'     => ['Shell San-Pédro', 'Total San-Pédro'],
            'Daloa'         => ['Total Daloa', 'Vivo Daloa'],
            'Man'           => ['Shell Man', 'Total Man'],
            'Korhogo'       => ['Total Korhogo', 'Vivo Korhogo'],
        ];

        $cityStations = $stations[$city] ?? ['Total', 'Shell', 'Vivo'];
        return $cityStations[array_rand($cityStations)];
    }
}


