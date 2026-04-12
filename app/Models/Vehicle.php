<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Vehicle extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'plate_number',
        'model',
        'capacity',
        'fuel_consumption_per_100km',
        'current_mileage_km',
        'last_maintenance_km',
        'maintenance_interval_km',
        'status',
        'notes',
    ];

    protected $casts = [
        'capacity'                    => 'integer',
        'fuel_consumption_per_100km'  => 'decimal:2',
        'current_mileage_km'          => 'decimal:2',
        'last_maintenance_km'         => 'decimal:2',
        'maintenance_interval_km'     => 'decimal:2',
    ];

    // ── Relations ────────────────────────────────────────────────────────

    public function departures(): HasMany
    {
        return $this->hasMany(Departure::class);
    }

    // ── Scopes ────────────────────────────────────────────────────────────

    public function scopeAvailable($query)
    {
        return $query->where('status', 'available');
    }

    // ── Helpers ───────────────────────────────────────────────────────────

    /**
     * Kilomètres restants avant la prochaine maintenance.
     */
    public function kmBeforeNextMaintenance(): float
    {
        $nextServiceAt = $this->last_maintenance_km + $this->maintenance_interval_km;
        return max(0, $nextServiceAt - $this->current_mileage_km);
    }

    /**
     * Vérifie si le véhicule a besoin de maintenance.
     */
    public function needsMaintenance(float $alertThresholdKm = 500): bool
    {
        return $this->kmBeforeNextMaintenance() <= $alertThresholdKm;
    }

    public function isAvailable(): bool
    {
        return $this->status === 'available';
    }
}
