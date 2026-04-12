<?php
// ══════════════════════════════════════════════════════════════════════════
// 2024_01_02_000004_create_driver_documents_table.php
// ══════════════════════════════════════════════════════════════════════════
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('driver_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('driver_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['license', 'medical', 'contract', 'other']);
            $table->string('file_path', 255);
            $table->date('expires_at')->nullable();
            $table->boolean('is_verified')->default(false)
                  ->comment('Vérifié par RH');
            $table->dateTime('uploaded_at');
            $table->timestamps();

            $table->index(['driver_id', 'type']);
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('driver_documents');
    }
};


// ══════════════════════════════════════════════════════════════════════════
// 2024_01_02_000005_create_driver_monthly_scores_table.php
// ══════════════════════════════════════════════════════════════════════════
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('driver_monthly_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('driver_id')->constrained()->cascadeOnDelete();
            $table->date('month')->comment('Toujours le 1er du mois: 2025-01-01');
            $table->unsignedSmallInteger('trips_count')->default(0);
            $table->decimal('total_distance_km', 10, 2)->default(0);
            $table->decimal('avg_eco_score', 4, 2)->nullable();
            $table->decimal('fuel_savings_liters', 8, 2)->default(0)
                  ->comment('Théorique - réel (positif = économies)');
            $table->unsignedSmallInteger('total_overspeed_events')->default(0);
            $table->decimal('avg_delay_min', 6, 2)->default(0);
            $table->unsignedSmallInteger('rank')->nullable()
                  ->comment('Classement mensuel calculé par AggregateMonthlyScores job');
            $table->decimal('bonus_fcfa', 12, 2)->nullable();
            $table->timestamps();

            // Un seul score par chauffeur par mois
            $table->unique(['driver_id', 'month']);
            $table->index(['month', 'rank']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('driver_monthly_scores');
    }
};
