<?php

// ══════════════════════════════════════════════════════════════════════════
// app/Models/DriverTripStats.php
// ══════════════════════════════════════════════════════════════════════════
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DriverTripStats extends Model
{
    protected $fillable = [
        'driver_id', 'departure_id',
        'distance_km', 'fuel_consumed_liters', 'fuel_theoretical_liters',
        'eco_score', 'overspeed_events', 'duration_min', 'delay_min',
        'recorded_at',
    ];

    protected $casts = [
        'distance_km'              => 'decimal:2',
        'fuel_consumed_liters'     => 'decimal:2',
        'fuel_theoretical_liters'  => 'decimal:2',
        'eco_score'                => 'decimal:2',
        'recorded_at'              => 'datetime',
    ];

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }

    public function departure(): BelongsTo
    {
        return $this->belongsTo(Departure::class);
    }

    public function fuelOverconsumptionPercent(): float
    {
        if (!$this->fuel_theoretical_liters) return 0;
        return round(
            (($this->fuel_consumed_liters - $this->fuel_theoretical_liters)
            / $this->fuel_theoretical_liters) * 100,
            1
        );
    }
}
