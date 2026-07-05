<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('incident_quality_scores', function (Blueprint $table) {
            $table->id();
            $table->enum('entity_type', ['driver', 'vehicle', 'route'])
                  ->comment('Type d\'entité évaluée');
            $table->unsignedBigInteger('entity_id')
                  ->comment('ID du chauffeur, véhicule ou ligne');
            $table->date('month')->comment('Toujours le 1er du mois');
            $table->unsignedSmallInteger('incidents_count')->default(0);
            $table->unsignedSmallInteger('critical_count')->default(0);
            $table->unsignedSmallInteger('high_count')->default(0);
            $table->decimal('total_financial_impact', 12, 2)->default(0);
            $table->decimal('quality_score', 5, 2)->default(100)
                  ->comment('Score 0-100: 100 = aucun incident');
            $table->decimal('avg_resolution_hours', 6, 2)->nullable()
                  ->comment('Temps moyen de résolution en heures');
            $table->timestamps();

            // Un seul score par entité par mois
            $table->unique(['entity_type', 'entity_id', 'month']);
            $table->index(['entity_type', 'month', 'quality_score']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incident_quality_scores');
    }
};
