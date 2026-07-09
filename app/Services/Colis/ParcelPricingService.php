<?php
// ══════════════════════════════════════════════════════════════════════════
// app/Services/Colis/ParcelPricingService.php
// ══════════════════════════════════════════════════════════════════════════
namespace App\Services\Colis;

class ParcelPricingService
{
    // Tarifs illustratifs — à ajuster, même esprit que
    // FuelValidationService::ANOMALY_THRESHOLD (constantes, pas de config DB).
    private const RATE_PER_KG_FCFA = 500;
    private const MINIMUM_FEE_FCFA = 1000;
    private const INSURANCE_RATE   = 0.02; // 2% de la valeur déclarée

    /**
     * Sépare le prix en deux composantes distinctes pour rester économiquement
     * cohérent quel que soit le colis :
     * - transport (poids) : ce qui paie réellement l'occupation de la soute
     * - assurance (valeur) : ce qui couvre le risque de perte/casse
     *
     * @return array{transport_fee_fcfa: float, insurance_fee_fcfa: float, total_fee_fcfa: float}
     */
    public function calculate(float $weightKg, float $declaredValue): array
    {
        $transport = max(self::MINIMUM_FEE_FCFA, $weightKg * self::RATE_PER_KG_FCFA);
        $insurance = $declaredValue * self::INSURANCE_RATE;

        return [
            'transport_fee_fcfa' => round($transport, 2),
            'insurance_fee_fcfa' => round($insurance, 2),
            'total_fee_fcfa'     => round($transport + $insurance, 2),
        ];
    }
}
