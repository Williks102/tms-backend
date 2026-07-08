<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounting_entry_lines', function (Blueprint $table) {
            $table->id();

            $table->foreignId('entry_id')->constrained('accounting_entries')->cascadeOnDelete();
            $table->foreignId('account_id')->constrained('accounting_accounts');

            // Une ligne n'a jamais débit ET crédit renseignés en même temps —
            // contrainte appliquée par AccountingEntryService, pas en DB.
            $table->decimal('debit', 14, 2)->default(0);
            $table->decimal('credit', 14, 2)->default(0);
            $table->string('label')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_entry_lines');
    }
};
