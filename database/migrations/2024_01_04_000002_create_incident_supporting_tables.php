<?php
// ══════════════════════════════════════════════════════════════════════════
// 2024_01_04_000002_create_incident_actions_table.php
// ══════════════════════════════════════════════════════════════════════════
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('incident_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('incident_id')
                  ->constrained()->cascadeOnDelete();
            $table->enum('action_type', [
                'repair',               // Réparation sur place ou en garage
                'medical',              // Intervention médicale
                'police',               // Déclaration de police
                'tow',                  // Remorquage du véhicule
                'replacement_vehicle',  // Véhicule de remplacement envoyé
                'passenger_support',    // Prise en charge des passagers
                'other',
            ]);
            $table->text('description');
            $table->foreignId('taken_by')->constrained('users');
            $table->dateTime('taken_at');
            $table->decimal('cost_fcfa', 10, 2)->nullable();
            $table->timestamps();

            $table->index('incident_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incident_actions');
    }
};


// ══════════════════════════════════════════════════════════════════════════
// 2024_01_04_000003_create_incident_media_table.php
// ══════════════════════════════════════════════════════════════════════════
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('incident_media', function (Blueprint $table) {
            $table->id();
            $table->foreignId('incident_id')
                  ->constrained()->cascadeOnDelete();
            $table->string('file_path', 255);
            $table->enum('file_type', [
                'photo',          // Photo de l'incident
                'video',          // Vidéo
                'document',       // Document administratif
                'police_report',  // Rapport de police
            ]);
            $table->string('description', 200)->nullable();
            $table->foreignId('uploaded_by')->constrained('users');
            $table->dateTime('uploaded_at');
            $table->timestamps();

            $table->index('incident_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incident_media');
    }
};


// ══════════════════════════════════════════════════════════════════════════
// 2024_01_04_000004_create_incident_quality_scores_table.php
// ══════════════════════════════════════════════════════════════════════════
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
