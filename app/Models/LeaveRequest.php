<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class LeaveRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'employable_type',
        'employable_id',
        'type',
        'start_date',
        'end_date',
        'reason',
        'status',
        'requested_at',
        'decided_by',
        'decided_at',
        'decision_notes',
    ];

    protected $casts = [
        'start_date'    => 'date',
        'end_date'      => 'date',
        'requested_at'  => 'datetime',
        'decided_at'    => 'datetime',
    ];

    // ── Relations ────────────────────────────────────────────────────────

    public function employable(): MorphTo
    {
        return $this->morphTo();
    }

    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    // ── Scopes ───────────────────────────────────────────────────────────

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeForEmployable($query, Model $employable)
    {
        return $query->where('employable_type', $employable::class)
            ->where('employable_id', $employable->getKey());
    }

    // ── Helpers ──────────────────────────────────────────────────────────

    /**
     * True si la période du congé inclut aujourd'hui.
     */
    public function overlapsToday(): bool
    {
        $today = Carbon::today();

        return $this->start_date->lte($today) && $this->end_date->gte($today);
    }
}
