<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('system_alerts', function (Blueprint $table) {
            $table->id();
            $table->enum('type', [
                'fuel_anomaly',      // Bon carburant anormal
                'maintenance_due',   // Maintenance imminente ou dépassée
                'doc_expiry',        // Document chauffeur expirant
                'overspeed',         // Excès de vitesse signalé
                'no_gate',           // Aucun quai disponible
                'driver_unavailable',// Chauffeur non conforme (repos)
            ]);
            $table->enum('severity', ['info', 'warning', 'critical']);
            // Polymorphique: peut pointer vers Vehicle, Driver, Departure...
            $table->string('entity_type', 100)->comment('App\\Models\\Vehicle');
            $table->unsignedBigInteger('entity_id');
            $table->text('message')->comment('Message lisible par le gestionnaire');
            $table->json('metadata')->nullable()
                  ->comment('Données contextuelles: voucher_id, ratio, km_remaining...');
            $table->boolean('is_resolved')->default(false);
            $table->dateTime('resolved_at')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // Index pour le dashboard: alertes actives triées par sévérité
            $table->index(['is_resolved', 'severity', 'created_at']);
            $table->index(['entity_type', 'entity_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('system_alerts');
    }
};
