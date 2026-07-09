<?php
// ══════════════════════════════════════════════════════════════════════════
// app/Models/AppNotification.php
// Nommé AppNotification (table app_notifications) — délibérément distinct
// du système de notifications natif Laravel (que le trait Notifiable sur
// User pourrait un jour utiliser), pour ne jamais entrer en collision.
// ══════════════════════════════════════════════════════════════════════════
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AppNotification extends Model
{
    protected $table = 'app_notifications';

    protected $fillable = ['user_id', 'type', 'title', 'body', 'data', 'read_at'];

    protected $casts = [
        'data'    => 'array',
        'read_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeUnread($query)
    {
        return $query->whereNull('read_at');
    }
}
