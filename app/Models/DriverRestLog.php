<?php
// ══════════════════════════════════════════════════════════════════════════
// app/Models/DriverRestLog.php
// ══════════════════════════════════════════════════════════════════════════
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DriverRestLog extends Model
{
    protected $fillable = [
        'driver_id', 'departure_id',
        'duty_start', 'duty_end', 'duty_duration_min',
        'rest_start', 'rest_end', 'rest_duration_min',
        'is_compliant',
    ];

    protected $casts = [
        'duty_start'    => 'datetime',
        'duty_end'      => 'datetime',
        'rest_start'    => 'datetime',
        'rest_end'      => 'datetime',
        'is_compliant'  => 'boolean',
    ];

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }

    public function departure(): BelongsTo
    {
        return $this->belongsTo(Departure::class);
    }

    public function isRestComplete(): bool
    {
        return $this->rest_end !== null;
    }

    public function restDurationMinutes(): ?int
    {
        if (!$this->rest_start) return null;
        $end = $this->rest_end ?? now();
        return (int) $this->rest_start->diffInMinutes($end);
    }
}
