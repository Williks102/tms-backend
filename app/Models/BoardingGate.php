<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class BoardingGate extends Model
{
    use HasFactory;

    protected $fillable = [
        'station_name',
        'gate_code',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    // ── Relations ────────────────────────────────────────────────────────

    public function departures(): HasMany
    {
        return $this->hasMany(Departure::class, 'boarding_gate', 'gate_code');
    }

    // ── Scopes ────────────────────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
