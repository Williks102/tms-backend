<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// L'éco-score est plafonné à 100 (40 + 30 + 30 pts) mais la colonne était en
// decimal(4,2), qui ne peut pas stocker 100.00 (5 chiffres significatifs).
// Voir EcoScoreService::calculate() — la formule est désormais bornée à 100.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('driver_trip_stats', function (Blueprint $table) {
            $table->decimal('eco_score', 5, 2)->nullable()->change();
        });

        Schema::table('driver_monthly_scores', function (Blueprint $table) {
            $table->decimal('avg_eco_score', 5, 2)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('driver_trip_stats', function (Blueprint $table) {
            $table->decimal('eco_score', 4, 2)->nullable()->change();
        });

        Schema::table('driver_monthly_scores', function (Blueprint $table) {
            $table->decimal('avg_eco_score', 4, 2)->nullable()->change();
        });
    }
};
