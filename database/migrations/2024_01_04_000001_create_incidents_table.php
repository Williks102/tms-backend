<?php
// ══════════════════════════════════════════════════════════════════════════
// 2024_01_04_000001_create_incidents_table.php
// ══════════════════════════════════════════════════════════════════════════
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('incidents', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 20)->unique()
                  ->comment('Généré auto: INC-AAAA-NNNN');
            $table->foreignId('departure_id')
                  ->nullable()->constrained()->nullOnDelete()
                  ->comment('Null si incident hors voyage');
            $table->foreignId('vehicle_id')
                  ->constrained()->restrictOnDelete();
            $table->foreignId('driver_id')
                  ->constrained()->restrictOnDelete();
            $table->foreignId('reported_by')
                  ->constrained('users')->restrictOnDelete();
            $table->enum('category', [
                'mechanical',  // Panne mécanique
                'accident',    // Accident de circulation
                'passenger',   // Conflit ou incident passager
                'road',        // Route barrée, météo, embouteillage
                'driver',      // Malaise, infraction chauffeur
                'other',
            ]);
            $table->enum('severity', ['low', 'medium', 'high', 'critical']);
            $table->string('title', 200);
            $table->text('description');
            $table->string('location', 200)->nullable();
            $table->dateTime('occurred_at');
            $table->enum('status', [
                'open',          // Signalé, pas encore traité
                'investigating', // En cours d'investigation
                'resolved',      // Résolu
                'closed',        // Clôturé définitivement
            ])->default('open');
            $table->text('resolution_notes')->nullable();
            $table->dateTime('resolved_at')->nullable();
            $table->foreignId('resolved_by')
                  ->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('financial_impact_fcfa', 12, 2)->nullable()
                  ->comment('Coût total estimé: réparations + retard + compensation');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'severity']);
            $table->index(['driver_id', 'occurred_at']);
            $table->index(['vehicle_id', 'occurred_at']);
            $table->index('occurred_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incidents');
    }
};
