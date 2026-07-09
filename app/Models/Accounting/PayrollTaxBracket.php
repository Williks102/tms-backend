<?php

namespace App\Models\Accounting;

use Illuminate\Database\Eloquent\Model;

class PayrollTaxBracket extends Model
{
    protected $fillable = [
        'type', 'min_fcfa', 'max_fcfa', 'rate_percent', 'label', 'sort_order',
    ];

    protected $casts = [
        'min_fcfa'     => 'decimal:2',
        'max_fcfa'     => 'decimal:2',
        'rate_percent' => 'decimal:2',
    ];
}
