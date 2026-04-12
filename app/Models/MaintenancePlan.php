<?php
// ══════════════════════════════════════════════════════════════════════════
// app/Models/MaintenancePlan.php
// ══════════════════════════════════
// ══════════════════════════════════════════════════════════════════════════
// app/Models/MaintenancePlan.php
// ══════════════════════════════════════════════════════════════════════════
namespace App\Models;
 
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
 
class MaintenancePlan extends Model
{
    protected $fillable = [
        'vehicle_id', 'type', 'trigger_km', 'trigger_date',
        'interval_km', 'interval_days', 'alert_km_before',
        'status', 'estimated_cost', 'notes',
    ];
 
    protected $casts = [
        'trigger_km'      => 'decimal:2',
        'trigger_date'    => 'date',
        'interval_km'     => 'decimal:2',
        'alert_km_before' => 'decimal:2',
        'estimated_cost'  => 'decimal:2',
    ];
 
    public function vehicle(): BelongsTo { return $this->belongsTo(Vehicle::class); }
    public function records(): HasMany   { return $this->hasMany(MaintenanceRecord::class); }
 
    public function scopeActive($query)
    {
        return $query->whereIn('status', ['upcoming', 'due']);
    }
 
    public function kmRemaining(Vehicle $vehicle): ?float
    {
        if (!$this->trigger_km) return null;
        return max(0, $this->trigger_km - $vehicle->current_mileage_km);
    }
}