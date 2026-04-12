<?php
// ══════════════════════════════════════════════════════════════════════════
// app/Services/Fuel/MaintenanceAlertService.php
// ══════════════════════════════════════════════════════════════════════════
namespace App\Services\Fuel;

use App\Models\MaintenancePlan;
use App\Models\Vehicle;

class MaintenanceAlertService
{
    public function __construct(
        private readonly AlertDispatcher $alertDispatcher,
    ) {}

    /**
     * Vérifie tous les plans de maintenance d'un véhicule
     * et crée les alertes nécessaires.
     * Appelé après chaque enregistrement de consommation.
     */
    public function checkVehicle(Vehicle $vehicle): void
    {
        $plans = MaintenancePlan::where('vehicle_id', $vehicle->id)
            ->active()
            ->get();

        foreach ($plans as $plan) {
            $this->checkPlan($vehicle, $plan);
        }
    }

    private function checkPlan(Vehicle $vehicle, MaintenancePlan $plan): void
    {
        if (!$plan->trigger_km) return;

        $kmRemaining = $plan->trigger_km - $vehicle->current_mileage_km;

        // Seuil dépassé → critique
        if ($kmRemaining <= 0) {
            $plan->update(['status' => 'overdue']);

            $this->alertDispatcher->create(
                type:     'maintenance_due',
                severity: 'critical',
                entity:   $vehicle,
                message:  sprintf(
                    '[CRITIQUE] %s dépassée de %d km — %s',
                    $this->typeLabel($plan->type),
                    abs($kmRemaining),
                    $vehicle->plate_number
                ),
                metadata: [
                    'plan_id'      => $plan->id,
                    'type'         => $plan->type,
                    'km_overdue'   => abs($kmRemaining),
                    'trigger_km'   => $plan->trigger_km,
                    'current_km'   => $vehicle->current_mileage_km,
                ]
            );
            return;
        }

        // Dans la zone d'alerte → warning
        if ($kmRemaining <= $plan->alert_km_before) {
            $plan->update(['status' => 'due']);

            $this->alertDispatcher->create(
                type:     'maintenance_due',
                severity: 'warning',
                entity:   $vehicle,
                message:  sprintf(
                    '%s dans %d km — %s (prévoir intervention)',
                    $this->typeLabel($plan->type),
                    $kmRemaining,
                    $vehicle->plate_number
                ),
                metadata: [
                    'plan_id'      => $plan->id,
                    'type'         => $plan->type,
                    'km_remaining' => $kmRemaining,
                ]
            );
        }
    }

    private function typeLabel(string $type): string
    {
        return match ($type) {
            'oil_change'   => 'Vidange',
            'tire'         => 'Changement pneus',
            'brake'        => 'Révision freins',
            'full_service' => 'Révision complète',
            default        => 'Maintenance',
        };
    }
}
