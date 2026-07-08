<?php

namespace App\Models\Accounting;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Payslip extends Model
{
    protected $fillable = [
        'employable_type', 'employable_id', 'period',
        'gross_amount_fcfa', 'deductions_amount_fcfa', 'net_amount_fcfa',
        'status', 'validated_at', 'paid_at',
        'validation_entry_id', 'payment_entry_id',
    ];

    protected $casts = [
        'gross_amount_fcfa'      => 'decimal:2',
        'deductions_amount_fcfa' => 'decimal:2',
        'net_amount_fcfa'        => 'decimal:2',
        'validated_at'           => 'datetime',
        'paid_at'                => 'datetime',
    ];

    public function employable(): MorphTo
    {
        return $this->morphTo();
    }

    public function lines(): HasMany
    {
        return $this->hasMany(PayslipLine::class, 'payslip_id');
    }

    public function validationEntry(): BelongsTo
    {
        return $this->belongsTo(AccountingEntry::class, 'validation_entry_id');
    }

    public function paymentEntry(): BelongsTo
    {
        return $this->belongsTo(AccountingEntry::class, 'payment_entry_id');
    }

    public function scopeForEmployable($query, Model $employable)
    {
        return $query->where('employable_type', $employable::class)
            ->where('employable_id', $employable->getKey());
    }

    public function isDraft(): bool     { return $this->status === 'draft'; }
    public function isValidated(): bool { return $this->status === 'validated'; }
}
