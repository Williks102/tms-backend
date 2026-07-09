<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Quotient familial (parts fiscales ITS) — 1 = célibataire sans
            // enfant, jusqu'à 5 en pratique (marié + enfants à charge).
            // Défaut 1 : ne sous-estime jamais un salarié qui aurait droit
            // à plus de parts, à corriger manuellement par RH/comptable.
            $table->decimal('parts_fiscales', 3, 1)->default(1.0)->after('base_salary_fcfa');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('parts_fiscales');
        });
    }
};
