<?php
// ══════════════════════════════════════════════════════════════════════════
// app/Services/Incidents/IncidentAlertService.php
// ══════════════════════════════════════════════════════════════════════════
namespace App\Services\Incidents;

use App\Models\Incident;
use App\Services\Fuel\AlertDispatcher;
use Illuminate\Support\Facades\Log;

class IncidentAlertService
{
    public function __construct(
        private readonly AlertDispatcher $alertDispatcher,
    ) {}

    public function fireAlert(Incident $incident): void
    {
        // 1. Alerte dans system_alerts (visible sur le dashboard)
        $this->alertDispatcher->create(
            type:     'incident_critical',
            severity: 'critical',
            entity:   $incident->vehicle,
            message:  sprintf(
                '[INCIDENT CRITIQUE] %s — %s | %s | Lieu: %s',
                $incident->reference,
                $incident->title,
                $incident->vehicle->plate_number,
                $incident->location ?? 'Non précisé'
            ),
            metadata: [
                'incident_id' => $incident->id,
                'category'    => $incident->category,
                'driver'      => $incident->driver->fullName(),
                'occurred_at' => $incident->occurred_at->toIso8601String(),
            ]
        );

        // 2. Véhicule en maintenance forcée si mécanique ou accident
        if (in_array($incident->category, ['mechanical', 'accident'])) {
            $incident->vehicle->update(['status' => 'maintenance']);
            Log::warning("Véhicule {$incident->vehicle->plate_number} mis en maintenance suite à incident {$incident->reference}");
        }

        Log::critical('Incident critique — alerte déclenchée', [
            'reference' => $incident->reference,
            'vehicle'   => $incident->vehicle->plate_number,
            'location'  => $incident->location,
        ]);
    }
}
