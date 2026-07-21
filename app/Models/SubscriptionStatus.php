<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

// Singleton (une seule ligne, id=1) — voir migration create_subscription_status_table
// et App\Http\Middleware\EnsureSubscriptionActive. Géré uniquement via la
// commande artisan tms:subscription (console Railway du client), jamais
// exposé via l'API/UI applicative.
class SubscriptionStatus extends Model
{
    protected $table = 'subscription_status';

    protected $fillable = ['status', 'paid_until', 'note'];

    protected $casts = [
        'paid_until' => 'date',
    ];

    // Pas de firstOrCreate(['id' => 1], ...) : 'id' n'est pas fillable, la
    // création silencieuse ignorerait la contrainte id=1 — inoffensif sur une
    // table vide (le premier id auto-incrémenté est 1 de toute façon) mais
    // fragile à lire. On se contente du premier (et seul) enregistrement.
    public static function current(): self
    {
        return static::first() ?? static::create(['status' => 'active']);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}
