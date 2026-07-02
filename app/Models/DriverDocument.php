<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DriverDocument extends Model
{
    protected $fillable = [
        'driver_id',
        'type',
        'file_path',
        'expires_at',
        'is_verified',
        'uploaded_at',
    ];

    protected $casts = [
        'expires_at' => 'date',
        'uploaded_at' => 'datetime',
        'is_verified' => 'boolean',
    ];

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }
}
