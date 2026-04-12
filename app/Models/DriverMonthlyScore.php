<?php
// ══════════════════════════════════════════════════════════════════════════
// app/Models/DriverMonthlyScore.php
// ══════════════════════════════════════════════════════════════════════════
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DriverMonthlyScore extends Model
{
    protected $fillable = [
        'driver_id', 'month', 'trips_count', 'total_distance_km',
        'avg_eco_score', 'fuel_savings_liters',
        'total_overspeed_events', 'avg_delay_min',
        'rank', 'bonus_fcfa',
    ];

    protected $casts = [
        'month'               => 'date',
        'avg_eco_score'       => 'decimal:2',
        'fuel_savings_liters' => 'decimal:2',
        'avg_delay_min'       => 'decimal:2',
        'bonus_fcfa'          => 'decimal:2',
    ];

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }
}
