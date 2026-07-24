<?php
// ══════════════════════════════════════════════════════════════════════════
// app/Models/FreightClient.php
// ══════════════════════════════════════════════════════════════════════════
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class FreightClient extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'company_name', 'contact_name', 'phone', 'email', 'address', 'notes',
    ];

    // ── Relations ──────────────────────────────────────────────────────

    public function shipments(): HasMany
    {
        return $this->hasMany(FreightShipment::class);
    }
}
