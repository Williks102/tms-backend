// ══════════════════════════════════════════════════════════════════════════
// app/Observers/FuelConsumptionObserver.php
// Enregistrer dans AppServiceProvider::boot():
//   FuelConsumptionLog::observe(FuelConsumptionObserver::class);
// ══════════════════════════════════════════════════════════════════════════
namespace App\Observers;

use App\Jobs\Fuel\CheckMaintenanceThresholds;
use App\Models\DriverTripStats;
use App\Models\FuelConsumptionLog;
use App\Services\Drivers\EcoScoreService;

class FuelConsumptionObserver
{
    public function __construct(
        private readonly EcoScoreService $ecoScoreService,
    ) {}

    public function created(FuelConsumptionLog $log): void
    {
        // 1. Met à jour le kilométrage réel du véhicule
        $log->vehicle->update([
            'current_mileage_km' => $log->mileage_after,
        ]);

        // 2. Calcule la consommation théorique pour ce voyage
        $theoretical = ($log->distance_km * $log->vehicle->fuel_consumption_per_100km) / 100;

        // 3. Crée ou met à jour les stats du chauffeur pour ce départ
        $stats = DriverTripStats::updateOrCreate(
            ['departure_id' => $log->departure_id],
            [
                'driver_id'                => $log->departure->driver_id,
                'distance_km'              => $log->distance_km,
                'fuel_consumed_liters'     => $log->liters_consumed,
                'fuel_theoretical_liters'  => $theoretical,
                'duration_min'             => $log->departure->actualDurationMinutes(),
                'delay_min'                => $log->departure->delayMinutes() ?? 0,
                'recorded_at'              => $log->recorded_at,
            ]
        );

        // 4. Calcule l'éco-score du chauffeur pour ce voyage
        if ($stats->driver_id) {
            $this->ecoScoreService->calculate($stats);
        }

        // 5. Vérifie les seuils de maintenance (job async pour ne pas bloquer)
        CheckMaintenanceThresholds::dispatch($log->vehicle);
    }
}