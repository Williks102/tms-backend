<?php
// ══════════════════════════════════════════════════════════════════════════
// app/Models/IncidentMedia.php
// ══════════════════════════════════════════════════════════════════════════
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IncidentMedia extends Model
{
    protected $fillable = [
        'incident_id', 'file_path', 'file_type',
        'description', 'uploaded_by', 'uploaded_at',
    ];

    protected $casts = [
        'uploaded_at' => 'datetime',
    ];

    public function incident(): BelongsTo   { return $this->belongsTo(Incident::class); }
    public function uploadedBy(): BelongsTo { return $this->belongsTo(User::class, 'uploaded_by'); }
}
