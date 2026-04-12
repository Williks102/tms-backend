<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class RouteStop extends Model
{
    use HasFactory;

    protected $fillable = [
        'route_id',
        'city_name',
        'stop_order',
        'distance_from_origin_km',
        'fare_from_origin',
    ];

    protected $casts = [
        'stop_order'               => 'integer',
        'distance_from_origin_km'  => 'decimal:2',
        'fare_from_origin'         => 'decimal:2',
    ];

    // ── Relations ────────────────────────────────────────────────────────

    public function route(): BelongsTo
    {
        return $this->belongsTo(Route::class);
    }
}
