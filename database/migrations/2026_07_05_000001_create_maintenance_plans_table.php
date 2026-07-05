<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maintenance_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicle_id')->constrained()->cascadeOnDelete();
            $table->enum('type', [
                'oil_change',    // Vidange
                'tire',          // Pneus
                'brake',         // Freins
                'full_service',  // Révision complète
                'other',
            ]);
            $table->decimal('trigger_km', 10, 2)->nullable()
                  ->comment('Kilométrage déclencheur (ex: 210000)');
            $table->date('trigger_date')->nullable()
                  ->comment('Ou à date fixe');
            $table->decimal('interval_km', 8, 2)->nullable()
                  ->comment('Intervalle récurrent (ex: 10000 km)');
            $table->unsignedInteger('interval_days')->nullable()
                  ->comment('Intervalle en jours');
            $table->decimal('alert_km_before', 6, 2)->default(500)
                  ->comment('Déclenchement alerte X km avant le seuil');
            $table->enum('status', ['upcoming', 'due', 'overdue', 'completed'])
                  ->default('upcoming');
            $table->decimal('estimated_cost', 10, 2)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['vehicle_id', 'status']);
            $table->index('trigger_km');
        });
    }

    public function down(): void { Schema::dropIfExists('maintenance_plans'); }
};
