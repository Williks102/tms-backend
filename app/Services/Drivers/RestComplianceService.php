<?php
// ══════════════════════════════════════════════════════════════════════════
// app/Services/Drivers/RestComplianceService.php
// ══════════════════════════════════════════════════════════════════════════
namespace App\Services\Drivers;

use App\Models\Driver;
use App\Models\DriverRestLog;
use App\Models\Departure;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class RestComplianceService
{
    // Repos minimum obligatoire entre deux prises de service (8 heures)
    private const MIN_REST_MINUTES = 480;

    /**
     * Vérifie si un chauffeur peut conduire à une datetime donnée.
     *
     * @return array{compliant: bool, message: string, available_at?: Carbon, rested_for_min?: int}
     */
    public function canDrive(Driver $driver, Carbon $nextDeparture): array
    {
        // Vérifie d'abord le statut du chauffeur
        if (!$driver->isAvailable()) {
            return [
                'compliant'    => false,
                'message'      => "Chauffeur en statut: {$driver->status}",
            ];
        }

        // Vérifie les documents obligatoires
        if ($driver->hasExpiredDocuments()) {
            return [
                'compliant' => false,
                'message'   => 'Permis ou visite médicale expirés — renouvellement requis',
            ];
        }

        // Récupère le dernier log de repos terminé
        $lastLog = DriverRestLog::where('driver_id', $driver->id)
            ->whereNotNull('rest_end')
            ->orderByDesc('rest_end')
            ->first();

        // Premier service enregistré → pas d'historique → autorisé
        if (!$lastLog) {
            return [
                'compliant'      => true,
                'message'        => 'Premier service — aucun historique de repos requis',
                'rested_for_min' => null,
            ];
        }

        $restEnd            = Carbon::parse($lastLog->rest_end);
        $minutesSinceRest   = (int) $restEnd->diffInMinutes($nextDeparture);

        if ($minutesSinceRest < self::MIN_REST_MINUTES) {
            $missingMinutes = self::MIN_REST_MINUTES - $minutesSinceRest;
            $availableAt    = $restEnd->copy()->addMinutes(self::MIN_REST_MINUTES);

            return [
                'compliant'    => false,
                'message'      => sprintf(
                    'Repos insuffisant: %dh%02d effectués sur %dh00 requis — disponible à %s',
                    intdiv($minutesSinceRest, 60),
                    $minutesSinceRest % 60,
                    intdiv(self::MIN_REST_MINUTES, 60),
                    $availableAt->format('d/m H:i')
                ),
                'available_at'   => $availableAt,
                'missing_minutes'=> $missingMinutes,
            ];
        }

        return [
            'compliant'      => true,
            'message'        => 'Temps de repos suffisant',
            'rested_for_min' => $minutesSinceRest,
        ];
    }

    /**
     * Démarre un log de service pour un chauffeur (au départ du bus).
     */
    public function startDuty(Driver $driver, Departure $departure): DriverRestLog
    {
        // Vérifie la conformité avant de créer le log
        $check = $this->canDrive($driver, $departure->departure_datetime);

        return DB::transaction(function () use ($driver, $departure, $check) {
            // Crée le log
            $log = DriverRestLog::create([
                'driver_id'    => $driver->id,
                'departure_id' => $departure->id,
                'duty_start'   => $departure->actual_departure ?? $departure->departure_datetime,
                'is_compliant' => $check['compliant'],
            ]);

            // Met le chauffeur en statut on_duty
            $driver->update(['status' => 'on_duty']);

            return $log;
        });
    }

    /**
     * Termine le service et démarre automatiquement le repos.
     */
    public function endDuty(Driver $driver, Departure $departure): DriverRestLog
    {
        $log = DriverRestLog::where('driver_id', $driver->id)
            ->where('departure_id', $departure->id)
            ->whereNull('duty_end')
            ->firstOrFail();

        $dutyEnd = $departure->actual_arrival ?? now();

        $log->update([
            'duty_end'          => $dutyEnd,
            'duty_duration_min' => (int) Carbon::parse($log->duty_start)->diffInMinutes($dutyEnd),
            'rest_start'        => $dutyEnd, // Le repos commence immédiatement
        ]);

        // Met le chauffeur en repos
        $driver->update(['status' => 'resting']);

        return $log->fresh();
    }

    /**
     * Termine le repos d'un chauffeur (il redevient disponible).
     */
    public function endRest(Driver $driver): DriverRestLog
    {
        $log = DriverRestLog::where('driver_id', $driver->id)
            ->whereNotNull('rest_start')
            ->whereNull('rest_end')
            ->latest('rest_start')
            ->firstOrFail();

        $restEnd = now();

        $log->update([
            'rest_end'          => $restEnd,
            'rest_duration_min' => (int) Carbon::parse($log->rest_start)->diffInMinutes($restEnd),
        ]);

        $driver->update(['status' => 'available']);

        return $log->fresh();
    }

    /**
     * Retourne les chauffeurs disponibles à une datetime donnée.
     */
    public function getAvailableDrivers(Carbon $at): \Illuminate\Support\Collection
    {
        return \App\Models\Driver::available()
            ->get()
            ->filter(fn(Driver $d) => $this->canDrive($d, $at)['compliant'])
            ->values();
    }
}