<?php
// ══════════════════════════════════════════════════════════════════════════
// app/Models/Incident.php
// ══════════════════════════════════════════════════════════════════════════
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Incident extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'reference', 'departure_id', 'vehicle_id', 'driver_id',
        'reported_by', 'category', 'severity', 'title', 'description',
        'location', 'occurred_at', 'status', 'resolution_notes',
        'resolved_at', 'resolved_by', 'financial_impact_fcfa',
    ];

    protected $casts = [
        'occurred_at'          => 'datetime',
        'resolved_at'          => 'datetime',
        'financial_impact_fcfa'=> 'decimal:2',
    ];

    // ── Relations ──────────────────────────────────────────────────────

    public function departure(): BelongsTo { return $this->belongsTo(Departure::class); }
    public function vehicle(): BelongsTo   { return $this->belongsTo(Vehicle::class); }
    public function driver(): BelongsTo    { return $this->belongsTo(Driver::class); }
    public function reportedBy(): BelongsTo{ return $this->belongsTo(User::class, 'reported_by'); }
    public function resolvedBy(): BelongsTo{ return $this->belongsTo(User::class, 'resolved_by'); }
    public function actions(): HasMany     { return $this->hasMany(IncidentAction::class)->latest('taken_at'); }
    public function media(): HasMany       { return $this->hasMany(IncidentMedia::class)->latest('uploaded_at'); }

    // ── Scopes ─────────────────────────────────────────────────────────

    public function scopeOpen($query)     { return $query->where('status', 'open'); }
    public function scopeCritical($query) { return $query->where('severity', 'critical'); }
    public function scopeActive($query)   { return $query->whereNotIn('status', ['closed']); }

    public function scopeForDriver($query, int $driverId)
    {
        return $query->where('driver_id', $driverId);
    }

    public function scopeForVehicle($query, int $vehicleId)
    {
        return $query->where('vehicle_id', $vehicleId);
    }

    public function scopeForPeriod($query, \Carbon\Carbon $from, \Carbon\Carbon $to)
    {
        return $query->whereBetween('occurred_at', [$from, $to]);
    }

    // ── Helpers ────────────────────────────────────────────────────────

    public function isCritical(): bool  { return $this->severity === 'critical'; }
    public function isOpen(): bool      { return $this->status === 'open'; }
    public function isResolved(): bool  { return in_array($this->status, ['resolved', 'closed']); }

    public function resolutionTimeHours(): ?float
    {
        if (!$this->resolved_at) return null;
        return round($this->occurred_at->diffInHours($this->resolved_at), 1);
    }

    public function totalActionsCost(): float
    {
        return (float) $this->actions->sum('cost_fcfa');
    }
}