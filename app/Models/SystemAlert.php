<?php
// ══════════════════════════════════════════════════════════════════════════
// app/Models/SystemAlert.php
// ══════════════════════════════════════════════════════════════════════════
namespace App\Models;
 
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
 
class SystemAlert extends Model
{
    protected $fillable = [
        'type', 'severity', 'entity_type', 'entity_id',
        'message', 'metadata', 'is_resolved',
        'resolved_at', 'resolved_by',
    ];
 
    protected $casts = [
        'metadata'    => 'array',
        'is_resolved' => 'boolean',
        'resolved_at' => 'datetime',
    ];
 
    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }
 
    public function scopeActive($query)
    {
        return $query->where('is_resolved', false);
    }
 
    public function scopeCritical($query)
    {
        return $query->where('severity', 'critical');
    }
 
    public function resolve(int $userId): void
    {
        $this->update([
            'is_resolved' => true,
            'resolved_at' => now(),
            'resolved_by' => $userId,
        ]);
    }
}
 