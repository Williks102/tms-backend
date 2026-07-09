<?php
// ══════════════════════════════════════════════════════════════════════════
// app/Models/ParcelMedia.php
// ══════════════════════════════════════════════════════════════════════════
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ParcelMedia extends Model
{
    protected $fillable = ['parcel_id', 'file_path', 'file_type', 'description', 'uploaded_by', 'uploaded_at'];

    protected $casts = [
        'uploaded_at' => 'datetime',
    ];

    public function parcel(): BelongsTo     { return $this->belongsTo(Parcel::class); }
    public function uploadedBy(): BelongsTo { return $this->belongsTo(User::class, 'uploaded_by'); }
}
