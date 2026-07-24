<?php
// ══════════════════════════════════════════════════════════════════════════
// app/Services/Fret/FreightPricingService.php
// ══════════════════════════════════════════════════════════════════════════
namespace App\Services\Fret;

use App\Models\FreightPricingSetting;

class FreightPricingService
{
    /**
     * Tarif lu en base (freight_pricing_settings, singleton) — éditable via
     * FreightPricingSettingController sans redéploiement, contrairement aux
     * constantes illustratives de ParcelPricingService. Valeurs par défaut
     * (150 FCFA/tonne-km, plancher 15000 FCFA) posées par la migration.
     *
     * @return array{price_fcfa: float}
     */
    public function calculate(float $weightTons, float $distanceKm): array
    {
        $settings = FreightPricingSetting::current();

        $price = max(
            (float) $settings->minimum_fee_fcfa,
            $weightTons * $distanceKm * (float) $settings->rate_per_ton_km_fcfa,
        );

        return ['price_fcfa' => round($price, 2)];
    }
}
