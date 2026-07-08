<?php

namespace App\Models\Accounting;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AccountingAccount extends Model
{
    protected $fillable = ['code', 'label', 'class', 'normal_side', 'is_active'];

    protected $casts = [
        'class'     => 'integer',
        'is_active' => 'boolean',
    ];

    public function lines(): HasMany
    {
        return $this->hasMany(AccountingEntryLine::class, 'account_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public static function findByCode(string $code): self
    {
        return static::where('code', $code)->firstOrFail();
    }
}
