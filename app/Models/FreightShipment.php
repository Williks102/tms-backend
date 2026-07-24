<?php
// ══════════════════════════════════════════════════════════════════════════
// app/Models/FreightShipment.php
// ══════════════════════════════════════════════════════════════════════════
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class FreightShipment extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'reference', 'freight_client_id', 'vehicle_id', 'driver_id',
        'origin_city', 'destination_city', 'distance_km', 'weight_tons',
        'cargo_description', 'price_fcfa', 'status', 'payment_status',
        'scheduled_at', 'departed_at', 'delivered_at', 'cancellation_reason',
        'created_by',
    ];

    protected $casts = [
        'distance_km'   => 'decimal:2',
        'weight_tons'   => 'decimal:2',
        'price_fcfa'    => 'decimal:2',
        'scheduled_at'  => 'datetime',
        'departed_at'   => 'datetime',
        'delivered_at'  => 'datetime',
    ];

    // ── Relations ──────────────────────────────────────────────────────

    public function client(): BelongsTo    { return $this->belongsTo(FreightClient::class, 'freight_client_id'); }
    public function vehicle(): BelongsTo   { return $this->belongsTo(Vehicle::class); }
    public function driver(): BelongsTo    { return $this->belongsTo(Driver::class); }
    public function createdBy(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }

    // ── Scopes ─────────────────────────────────────────────────────────

    public function scopeActive($query) { return $query->where('status', '!=', 'cancelled'); }

    public function scopeForClient($query, int $freightClientId)
    {
        return $query->where('freight_client_id', $freightClientId);
    }

    // ── Helpers ────────────────────────────────────────────────────────

    public function isPending(): bool     { return $this->status === 'pending'; }
    public function isDelivered(): bool   { return $this->status === 'delivered'; }
    public function canBeCancelled(): bool { return in_array($this->status, ['pending', 'assigned', 'in_transit']); }
}
