<?php
// ══════════════════════════════════════════════════════════════════════════
// app/Models/IncidentAction.php
// ══════════════════════════════════════════════════════════════════════════
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IncidentAction extends Model
{
    protected $fillable = [
        'incident_id', 'action_type', 'description',
        'taken_by', 'taken_at', 'cost_fcfa',
    ];

    protected $casts = [
        'taken_at'  => 'datetime',
        'cost_fcfa' => 'decimal:2',
    ];

    public function incident(): BelongsTo { return $this->belongsTo(Incident::class); }
    public function takenBy(): BelongsTo  { return $this->belongsTo(User::class, 'taken_by'); }
}