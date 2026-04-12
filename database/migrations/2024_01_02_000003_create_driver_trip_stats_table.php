<?php
// ══════════════════════════════════════════════════════════════════════════
// 2024_01_02_000003_create_driver_trip_stats_table.php
// ══════════════════════════════════════════════════════════════════════════
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('driver_trip_stats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('driver_id')->constrained()->cascadeOnDelete();
            $table->foreignId('departure_id')->constrained()->cascadeOnDelete();
            $table->decimal('distance_km', 8, 2)->default(0);
            $table->decimal('fuel_consumed_liters', 6, 2)->default(0);
            $table->decimal('fuel_theoretical_liters', 6, 2)->default(0);
            $table->decimal('eco_score', 4, 2)->nullable()
                  ->comment('0 à 100 — calculé par EcoScoreService');
            $table->unsignedSmallInteger('overspeed_events')->default(0);
            $table->unsignedInteger('duration_min')->nullable();
            $table->integer('delay_min')->default(0)
                  ->comment('Négatif = en avance, positif = en retard');
            $table->dateTime('recorded_at');
            $table->timestamps();

            // Un seul enregistrement de stats par départ
            $table->unique('departure_id');
            $table->index(['driver_id', 'recorded_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('driver_trip_stats');
    }
};
