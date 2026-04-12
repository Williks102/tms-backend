<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Route extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'code',
        'name',
        'origin_city',
        'destination_city',
        'distance_km',
        'estimated_duration_min',
        'is_dynamic',
        'base_fare',
        'is_active',
    ];

    protected $casts = [
        'distance_km'            => 'decimal:2',
        'base_fare'              => 'decimal:2',
        'is_dynamic'             => 'boolean',
        'is_active'              => 'boolean',
        'estimated_duration_min' => 'integer',
    ];

    // ── Relations ────────────────────────────────────────────────────────

    public function stops(): HasMany
    {
        return $this->hasMany(RouteStop::class)->orderBy('stop_order');
    }

    public function scheduleTemplates(): HasMany
    {
        return $this->hasMany(ScheduleTemplate::class);
    }

    public function departures(): HasMany
    {
        return $this->hasMany(Departure::class);
    }

    // ── Scopes ────────────────────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeDynamic($query)
    {
        return $query->where('is_dynamic', true);
    }

    // ── Helpers ───────────────────────────────────────────────────────────

    /**
     * Calcule la consommation théorique de carburant pour cette ligne.
     * Utilisé par FuelValidationService.
     */
    public function theoreticalFuel(Vehicle $vehicle): float
    {
        return ($this->distance_km * $vehicle->fuel_consumption_per_100km) / 100;
    }
}
