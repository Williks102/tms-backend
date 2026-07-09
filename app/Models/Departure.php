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

    /**
     * Ouverture de l'embarquement, fixée à X minutes avant departure_datetime.
     * Purement indicatif — le passage au statut "boarding" reste une action
     * manuelle du personnel (voir DepartureService::VALID_TRANSITIONS).
     */
    public const BOARDING_LEAD_MINUTES = 30;

    protected $fillable = [
        'route_id',
        'vehicle_id',
        'driver_id',
        'schedule_template_id',
        'departure_datetime',
        'estimated_arrival',
        'actual_departure',
        'actual_arrival',
        'boarding_gate_id',
        'seats_available',
        'status',
        'cancellation_reason',
        'notes',
        'cargo_capacity_kg',
        'cargo_used_kg',
    ];

    protected $casts = [
        'departure_datetime' => 'datetime',
        'estimated_arrival'  => 'datetime',
        'actual_departure'   => 'datetime',
        'actual_arrival'     => 'datetime',
        'seats_available'    => 'integer',
        'cargo_capacity_kg'  => 'decimal:2',
        'cargo_used_kg'      => 'decimal:2',
    ];

    // `boarding_gate` n'est plus une colonne (voir boarding_gate_id + gate())
    // mais reste attendu comme champ par le frontend — $appends le fait figurer
    // dans toArray()/toJson() même quand le modèle est sérialisé directement
    // (sans passer par DepartureResource), ex: Ticket::with('departure...').
    protected $appends = ['boarding_gate', 'boarding_time', 'boarding_due'];

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

    // Nommée "gate" (pas "boardingGate") pour éviter toute collision avec
    // l'accesseur getBoardingGateAttribute() ci-dessous : Eloquent résout les
    // mutateurs par version "studly" du nom, et "boardingGate" ↔ "boarding_gate"
    // partagent la même forme studly ("BoardingGate"), ce qui créerait une
    // récursion infinie si la relation portait ce nom.
    public function gate(): BelongsTo
    {
        return $this->belongsTo(BoardingGate::class, 'boarding_gate_id');
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    public function parcels(): HasMany
    {
        return $this->hasMany(Parcel::class);
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

    /**
     * Code du quai (ex: "Q1"), résolu via la relation gate().
     * Conserve la compatibilité de l'API/frontend qui attend `boarding_gate`
     * comme chaîne, alors que la colonne réelle est `boarding_gate_id`.
     */
    public function getBoardingGateAttribute(): ?string
    {
        return $this->gate?->gate_code;
    }

    /**
     * Heure d'ouverture prévue de l'embarquement (departure_datetime - 30 min).
     */
    public function getBoardingTimeAttribute(): ?Carbon
    {
        return $this->departure_datetime?->copy()->subMinutes(self::BOARDING_LEAD_MINUTES);
    }

    /**
     * True si l'heure d'embarquement prévue est passée mais que le départ
     * est toujours "scheduled" — sert à alerter le personnel qu'il doit
     * ouvrir l'embarquement manuellement (voir DepartureService::updateStatus).
     */
    public function isBoardingDue(): bool
    {
        return $this->status === 'scheduled'
            && $this->boarding_time !== null
            && $this->boarding_time->isPast();
    }

    public function getBoardingDueAttribute(): bool
    {
        return $this->isBoardingDue();
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
