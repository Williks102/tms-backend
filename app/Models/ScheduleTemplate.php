<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ScheduleTemplate extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'route_id',
        'departure_time',
        'days_of_week',
        'valid_from',
        'valid_until',
        'is_active',
    ];

    protected $casts = [
        'days_of_week' => 'array',   // JSON → tableau PHP automatiquement
        'valid_from'   => 'date',
        'valid_until'  => 'date',
        'is_active'    => 'boolean',
    ];

    // ── Relations ────────────────────────────────────────────────────────

    public function route(): BelongsTo
    {
        return $this->belongsTo(Route::class);
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

    public function scopeValidOn($query, Carbon $date)
    {
        return $query
            ->where('valid_from', '<=', $date)
            ->where(function ($q) use ($date) {
                $q->whereNull('valid_until')
                  ->orWhere('valid_until', '>=', $date);
            });
    }

    // ── Helpers ───────────────────────────────────────────────────────────

    /**
     * Vérifie si ce gabarit est actif un jour donné.
     */
    public function isActiveOn(Carbon $date): bool
    {
        // dayOfWeek: 0 = dimanche, 1 = lundi ... 6 = samedi (Carbon standard)
        return in_array($date->dayOfWeek, $this->days_of_week, true)
            && $date->gte($this->valid_from)
            && ($this->valid_until === null || $date->lte($this->valid_until));
    }

    /**
     * Retourne la datetime complète du départ pour une date donnée.
     */
    public function departureDateTimeFor(Carbon $date): Carbon
    {
        return $date->copy()->setTimeFromTimeString($this->departure_time);
    }
}
