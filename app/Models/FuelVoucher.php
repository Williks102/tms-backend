<?php
// ══════════════════════════════════════════════════════════════════════════
// app/Models/FuelVoucher.php
// ══════════════════════════════════════════════════════════════════════════
namespace App\Models;
 
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
 
class FuelVoucher extends Model
{
    protected $fillable = [
        'departure_id', 'driver_id', 'vehicle_id',
        'requested_liters', 'theoretical_liters', 'approved_liters',
        'fuel_type', 'price_per_liter', 'total_cost',
        'station_name', 'status', 'rejection_reason',
        'requested_at', 'approved_at', 'approved_by',
    ];
 
    protected $casts = [
        'requested_liters'   => 'decimal:2',
        'theoretical_liters' => 'decimal:2',
        'approved_liters'    => 'decimal:2',
        'price_per_liter'    => 'decimal:2',
        'total_cost'         => 'decimal:2',
        'requested_at'       => 'datetime',
        'approved_at'        => 'datetime',
    ];
 
    public function departure(): BelongsTo { return $this->belongsTo(Departure::class); }
    public function driver(): BelongsTo    { return $this->belongsTo(Driver::class); }
    public function vehicle(): BelongsTo   { return $this->belongsTo(Vehicle::class); }
    public function approvedBy(): BelongsTo{ return $this->belongsTo(User::class, 'approved_by'); }
 
    public function overConsumptionRatio(): float
    {
        return $this->theoretical_liters > 0
            ? round($this->requested_liters / $this->theoretical_liters, 2)
            : 0;
    }
 
    public function isPending(): bool  { return $this->status === 'pending'; }
    public function isApproved(): bool { return $this->status === 'approved'; }
}