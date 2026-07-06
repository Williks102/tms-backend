<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class MaintenanceRecord extends Model
{
    use HasFactory;

    protected $fillable = [
        'vehicle_id',
        'maintenance_plan_id',
        'type',
        'performed_at',
        'mileage_at_service',
        'garage_name',
        'cost_fcfa',
        'parts_replaced',
        'next_service_km',
        'performed_by',
        'invoice_path',
    ];

    protected $casts = [
        'performed_at'        => 'datetime',
        'mileage_at_service'  => 'decimal:2',
        'cost_fcfa'           => 'decimal:2',
        'parts_replaced'      => 'array',
        'next_service_km'     => 'decimal:2',
    ];

    // ── Relations ────────────────────────────────────────────────────────

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function maintenancePlan(): BelongsTo
    {
        return $this->belongsTo(MaintenancePlan::class);
    }

    public function performedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }
}
