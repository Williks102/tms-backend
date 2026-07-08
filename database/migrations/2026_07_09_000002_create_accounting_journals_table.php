<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounting_journals', function (Blueprint $table) {
            $table->id();

            // Journaux OHADA classiques — VE (ventes), AC (achats), CA (caisse),
            // BQ (banque), OD (opérations diverses). Seedés en dur (5 lignes).
            $table->string('code', 4)->unique();
            $table->string('label');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_journals');
    }
};
