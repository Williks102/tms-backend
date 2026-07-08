<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounting_accounts', function (Blueprint $table) {
            $table->id();

            // Plan comptable SYSCOHADA révisé — sous-ensemble seedé par
            // AccountingChartSeeder, extensible via POST /comptabilite/accounts.
            $table->string('code')->unique();
            $table->string('label');
            $table->unsignedTinyInteger('class'); // 1 à 8, classe SYSCOHADA
            $table->enum('normal_side', ['debit', 'credit']); // sens naturel du solde
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->index('class');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_accounts');
    }
};
