<?php

namespace App\Models\Accounting;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CashVoucher extends Model
{
    protected $fillable = [
        'reference', 'type', 'amount_fcfa', 'account_id', 'label', 'beneficiary_name',
        'status', 'requested_by', 'approved_by', 'approved_at', 'rejection_reason',
        'accounting_entry_id',
    ];

    protected $casts = [
        'amount_fcfa' => 'decimal:2',
        'approved_at' => 'datetime',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(AccountingAccount::class, 'account_id');
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function accountingEntry(): BelongsTo
    {
        return $this->belongsTo(AccountingEntry::class, 'accounting_entry_id');
    }

    public function isPending(): bool { return $this->status === 'pending'; }
    public function isSortie(): bool  { return $this->type === 'sortie'; }
}
