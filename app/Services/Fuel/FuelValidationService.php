<?php
// ══════════════════════════════════════════════════════════════════════════
// app/Services/Fuel/FuelValidationService.php
// ══════════════════════════════════════════════════════════════════════════
namespace App\Services\Fuel;

use App\Models\FuelVoucher;

class FuelValidationService
{
    // +15% au-dessus du théorique → bloque et crée une alerte
    private const ANOMALY_THRESHOLD = 1.15;

    public function __construct(
        private readonly AlertDispatcher $alertDispatcher,
    ) {}

    /**
     * Valide une demande de bon carburant.
     *
     * @return array{valid: bool, theoretical_liters: float, ratio: float, reason?: string}
     */
    public function validate(FuelVoucher $voucher): array
    {
        $route   = $voucher->departure->route;
        $vehicle = $voucher->vehicle;

        // Calcul théorique
        $theoretical = ($route->distance_km * $vehicle->fuel_consumption_per_100km) / 100;
        $voucher->update(['theoretical_liters' => round($theoretical, 2)]);

        $ratio = $voucher->requested_liters / max($theoretical, 0.1);

        if ($ratio > self::ANOMALY_THRESHOLD) {
            $this->alertDispatcher->create(
                type:     'fuel_anomaly',
                severity: 'warning',
                entity:   $vehicle,
                message:  sprintf(
                    'Bon carburant anormal: %.1fL demandé vs %.1fL théorique (+%d%%) — %s',
                    $voucher->requested_liters,
                    $theoretical,
                    round(($ratio - 1) * 100),
                    $vehicle->plate_number
                ),
                metadata: [
                    'voucher_id'   => $voucher->id,
                    'ratio'        => round($ratio, 2),
                    'departure_id' => $voucher->departure_id,
                    'route'        => $route->code,
                ]
            );

            return [
                'valid'               => false,
                'theoretical_liters'  => $theoretical,
                'ratio'               => round($ratio, 2),
                'reason'              => sprintf(
                    'Demande dépasse de %d%% le théorique (%.1fL) — justification obligatoire',
                    round(($ratio - 1) * 100),
                    $theoretical
                ),
            ];
        }

        return [
            'valid'              => true,
            'theoretical_liters' => $theoretical,
            'ratio'              => round($ratio, 2),
        ];
    }

    /**
     * Approuve un bon et calcule le coût total.
     */
    public function approve(FuelVoucher $voucher, float $approvedLiters, int $approvedBy): FuelVoucher
    {
        $totalCost = $approvedLiters * $voucher->price_per_liter;

        $voucher->update([
            'approved_liters' => $approvedLiters,
            'total_cost'      => $totalCost,
            'status'          => 'approved',
            'approved_at'     => now(),
            'approved_by'     => $approvedBy,
        ]);

        // Résout l'alerte fuel_anomaly si elle existait
        $this->alertDispatcher->resolveFor($voucher->vehicle, 'fuel_anomaly');

        return $voucher->fresh();
    }
}
