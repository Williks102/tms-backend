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
        'type',
        'model',
        'capacity',
        'fuel_consumption_per_100km',
        'current_mileage_km',
        'last_maintenance_km',
        'maintenance_interval_km',
        'status',
        'notes',
        'cargo_capacity_kg',
        'insurance_expires_at',
        'controle_technique_expires_at',
    ];

    protected $casts = [
        'capacity'                    => 'integer',
        'fuel_consumption_per_100km'  => 'decimal:2',
        'current_mileage_km'          => 'decimal:2',
        'last_maintenance_km'         => 'decimal:2',
        'maintenance_interval_km'     => 'decimal:2',
        'cargo_capacity_kg'           => 'decimal:2',
        'insurance_expires_at'          => 'date',
        'controle_technique_expires_at' => 'date',
    ];

    // ── Relations ────────────────────────────────────────────────────────

    public function departures(): HasMany
    {
        return $this->hasMany(Departure::class);
    }

    public function maintenancePlans(): HasMany
    {
        return $this->hasMany(MaintenancePlan::class);
    }

    public function maintenanceRecords(): HasMany
    {
        return $this->hasMany(MaintenanceRecord::class);
    }

    public function incidents(): HasMany
    {
        return $this->hasMany(Incident::class);
    }

    public function fuelVouchers(): HasMany
    {
        return $this->hasMany(FuelVoucher::class);
    }

    public function fuelConsumptionLogs(): HasMany
    {
        return $this->hasMany(FuelConsumptionLog::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(VehicleDocument::class);
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

    /**
     * Assurance ou contrôle technique expiré — même principe que
     * Driver::hasExpiredDocuments(), vérifié via des colonnes dédiées
     * (pas vehicle_documents.expires_at, qui peut contenir d'anciens
     * documents remplacés depuis).
     */
    public function hasExpiredDocuments(): bool
    {
        return ($this->insurance_expires_at !== null && $this->insurance_expires_at->isPast())
            || ($this->controle_technique_expires_at !== null && $this->controle_technique_expires_at->isPast());
    }
}
