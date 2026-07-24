<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

// Singleton (une seule ligne) — même pattern que SubscriptionStatus::current().
// Rend le tarif fret éditable via FreightPricingSettingController sans
// redéploiement (contrairement aux constantes de ParcelPricingService).
class FreightPricingSetting extends Model
{
    protected $table = 'freight_pricing_settings';

    protected $fillable = ['rate_per_ton_km_fcfa', 'minimum_fee_fcfa'];

    protected $casts = [
        'rate_per_ton_km_fcfa' => 'decimal:2',
        'minimum_fee_fcfa'     => 'decimal:2',
    ];

    // Valeurs par défaut passées explicitement (pas de create([]) qui insère
    // NULL plutôt que de laisser la base appliquer le DEFAULT de colonne) —
    // même pattern que SubscriptionStatus::current().
    public static function current(): self
    {
        return static::first() ?? static::create([
            'rate_per_ton_km_fcfa' => 150,
            'minimum_fee_fcfa'     => 15000,
        ]);
    }
}
