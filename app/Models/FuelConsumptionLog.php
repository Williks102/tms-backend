<?php
// ══════════════════════════════════════════════════════════════════════════
// app/Models/FuelConsumptionLog.php
// ══════════════════════════════════════════════════════════════════════════
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FuelConsumptionLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'vehicle_id',
        'departure_id',
        'fuel_voucher_id',
        'liters_consumed',
        'distance_km',
        'consumption_per_100km',
        'mileage_before',
        'mileage_after',
        'recorded_at',
        'recorded_by',
    ];

    protected $casts = [
        'liters_consumed' => 'decimal:2',
        'distance_km' => 'decimal:2',
        'consumption_per_100km' => 'decimal:2',
        'mileage_before' => 'decimal:2',
        'mileage_after' => 'decimal:2',
        'recorded_at' => 'datetime',
    ];

    // Relations
    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function departure(): BelongsTo
    {
        return $this->belongsTo(Departure::class);
    }

    public function fuelVoucher(): BelongsTo
    {
        return $this->belongsTo(FuelVoucher::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}