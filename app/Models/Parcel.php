<?php
// ══════════════════════════════════════════════════════════════════════════
// app/Models/Parcel.php
// ══════════════════════════════════════════════════════════════════════════
namespace App\Models;

use App\Models\Accounting\AccountingEntry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Parcel extends Model
{
    protected $fillable = [
        'tracking_number', 'pickup_code', 'departure_id', 'destination_stop_id',
        'sender_name', 'sender_phone', 'recipient_name', 'recipient_phone',
        'category', 'description', 'weight_kg', 'declared_value_fcfa',
        'transport_fee_fcfa', 'insurance_fee_fcfa', 'total_fee_fcfa',
        'payment_responsibility', 'payment_status', 'status',
        'registered_by', 'released_by',
        'registered_at', 'arrived_at', 'released_at', 'returned_at',
        'exception_reason', 'accounting_entry_id',
    ];

    protected $casts = [
        'weight_kg'           => 'decimal:2',
        'declared_value_fcfa' => 'decimal:2',
        'transport_fee_fcfa'  => 'decimal:2',
        'insurance_fee_fcfa'  => 'decimal:2',
        'total_fee_fcfa'      => 'decimal:2',
        'registered_at'       => 'datetime',
        'arrived_at'          => 'datetime',
        'released_at'         => 'datetime',
        'returned_at'         => 'datetime',
    ];

    // ── Relations ──────────────────────────────────────────────────────

    public function departure(): BelongsTo       { return $this->belongsTo(Departure::class); }
    public function destinationStop(): BelongsTo { return $this->belongsTo(RouteStop::class, 'destination_stop_id'); }
    public function registeredBy(): BelongsTo    { return $this->belongsTo(User::class, 'registered_by'); }
    public function releasedBy(): BelongsTo      { return $this->belongsTo(User::class, 'released_by'); }
    public function accountingEntry(): BelongsTo { return $this->belongsTo(AccountingEntry::class, 'accounting_entry_id'); }
    public function media(): HasMany             { return $this->hasMany(ParcelMedia::class); }

    // ── Scopes ─────────────────────────────────────────────────────────

    public function scopeActive($query) { return $query->where('status', '!=', 'annule'); }

    // ── Helpers ────────────────────────────────────────────────────────

    public function isPending(): bool      { return $this->status === 'enregistre'; }
    public function canBeReleased(): bool  { return $this->status === 'arrive'; }
}
