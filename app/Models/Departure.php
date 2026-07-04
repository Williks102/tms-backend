<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Departure extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'route_id',
        'vehicle_id',
        'driver_id',
        'schedule_template_id',
        'departure_datetime',
        'estimated_arrival',
        'actual_departure',
        'actual_arrival',
        'boarding_gate',
        'seats_available',
        'status',
        'cancellation_reason',
        'notes',
    ];

    protected $casts = [
        'departure_datetime' => 'datetime',
        'estimated_arrival'  => 'datetime',
        'actual_departure'   => 'datetime',
        'actual_arrival'     => 'datetime',
        'seats_available'    => 'integer',
    ];

    // ── Relations ────────────────────────────────────────────────────────

    public function route(): BelongsTo
    {
        return $this->belongsTo(Route::class);
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    /**
     * Relation vers Driver (défini dans le module Chauffeurs).
     * On évite l'import de la classe pour ne pas créer de couplage fort.
     */
    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }

    public function scheduleTemplate(): BelongsTo
    {
        return $this->belongsTo(ScheduleTemplate::class);
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    // ── Scopes ────────────────────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->whereNotIn('status', ['cancelled', 'arrived']);
    }

    public function scopeLive($query)
    {
        return $query->whereIn('status', ['boarding', 'departed'])
                     ->orderBy('departure_datetime');
    }

    public function scopeScheduled($query)
    {
        return $query->where('status', 'scheduled');
    }

    public function scopeForDate($query, Carbon $date)
    {
        return $query->whereDate('departure_datetime', $date);
    }

    public function scopeForPeriod($query, Carbon $from, Carbon $to)
    {
        return $query->whereBetween('departure_datetime', [$from, $to]);
    }

    public function scopeNotCancelled($query)
    {
        return $query->where('status', '!=', 'cancelled');
    }

    // ── Helpers ───────────────────────────────────────────────────────────

    public function isManual(): bool
    {
        return $this->schedule_template_id === null;
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    public function isAssigned(): bool
    {
        return $this->vehicle_id !== null && $this->driver_id !== null;
    }

    public function hasAvailableSeats(): bool
    {
        return $this->seats_available > 0;
    }

    public function isOpenForTicketSale(): bool
    {
        return in_array($this->status, ['scheduled', 'boarding']);
    }

    /**
     * Retard en minutes (négatif = en avance).
     */
    public function delayMinutes(): ?int
    {
        if (!$this->actual_arrival || !$this->estimated_arrival) {
            return null;
        }

        return (int) $this->estimated_arrival->diffInMinutes($this->actual_arrival, false);
    }

    /**
     * Durée réelle du voyage en minutes.
     */
    public function actualDurationMinutes(): ?int
    {
        if (!$this->actual_departure || !$this->actual_arrival) {
            return null;
        }

        return (int) $this->actual_departure->diffInMinutes($this->actual_arrival);
    }
}
