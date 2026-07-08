<?php

namespace App\Models\Accounting;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AccountingJournal extends Model
{
    protected $fillable = ['code', 'label'];

    public function entries(): HasMany
    {
        return $this->hasMany(AccountingEntry::class, 'journal_id');
    }
}
