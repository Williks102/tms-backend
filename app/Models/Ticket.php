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
        'reference', 'departure_id', 'destination_stop_id', 'passenger_name', 'passenger_phone',
        'passenger_email', 'seat_number', 'channel', 'payment_method', 'payment_channel',
        'payment_token', 'payment_session_id', 'payment_confirmed_at', 'payment_expires_at',
        'price_fcfa', 'status', 'sold_by', 'purchased_at', 'boarded_at', 'boarded_by', 'cancellation_reason',
    ];

    protected $casts = [
        'price_fcfa'            => 'decimal:2',
        'purchased_at'          => 'datetime',
        'boarded_at'            => 'datetime',
        'payment_confirmed_at'  => 'datetime',
        'payment_expires_at'    => 'datetime',
    ];

    // ── Relations ──────────────────────────────────────────────────────

    public function departure(): BelongsTo      { return $this->belongsTo(Departure::class); }
    public function soldBy(): BelongsTo         { return $this->belongsTo(User::class, 'sold_by'); }
    public function boardedBy(): BelongsTo      { return $this->belongsTo(User::class, 'boarded_by'); }
    public function destinationStop(): BelongsTo { return $this->belongsTo(RouteStop::class, 'destination_stop_id'); }

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
