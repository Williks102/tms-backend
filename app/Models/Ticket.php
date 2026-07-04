<?php
// ══════════════════════════════════════════════════════════════════════════
// app/Models/Ticket.php
// ══════════════════════════════════════════════════════════════════════════
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Ticket extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'reference', 'departure_id', 'passenger_name', 'passenger_phone',
        'seat_number', 'channel', 'payment_method', 'price_fcfa', 'status',
        'sold_by', 'purchased_at', 'boarded_at', 'cancellation_reason',
    ];

    protected $casts = [
        'price_fcfa'   => 'decimal:2',
        'purchased_at' => 'datetime',
        'boarded_at'   => 'datetime',
    ];

    // ── Relations ──────────────────────────────────────────────────────

    public function departure(): BelongsTo { return $this->belongsTo(Departure::class); }
    public function soldBy(): BelongsTo    { return $this->belongsTo(User::class, 'sold_by'); }

    // ── Scopes ─────────────────────────────────────────────────────────

    public function scopeActive($query)   { return $query->whereNotIn('status', ['cancelled', 'refunded']); }
    public function scopePhysical($query) { return $query->where('channel', 'physical'); }
    public function scopeOnline($query)   { return $query->where('channel', 'online'); }

    public function scopeForDeparture($query, int $departureId)
    {
        return $query->where('departure_id', $departureId);
    }

    // ── Helpers ────────────────────────────────────────────────────────

    public function isPhysical(): bool { return $this->channel === 'physical'; }
    public function isOnline(): bool   { return $this->channel === 'online'; }
    public function isBoarded(): bool  { return $this->status === 'boarded'; }
}
