<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicles', function (Blueprint $table) {
            $table->id();
            $table->string('plate_number', 20)->unique()->comment('Immatriculation');
            $table->string('model', 100);
            $table->unsignedSmallInteger('capacity')->comment('Nombre de places assises');
            $table->decimal('fuel_consumption_per_100km', 5, 2)
                  ->comment('Consommation théorique en L/100km');
            $table->decimal('current_mileage_km', 10, 2)->default(0)
                  ->comment('Mis à jour automatiquement après chaque voyage');
            $table->decimal('last_maintenance_km', 10, 2)->default(0);
            $table->decimal('maintenance_interval_km', 8, 2)->default(10000)
                  ->comment('Intervalle vidange/entretien en km');
            $table->enum('status', [
                'available',    // Disponible pour affectation
                'on_trip',      // En voyage
                'boarding',     // En chargement au quai
                'maintenance',  // En atelier
                'inactive',     // Retiré du service
            ])->default('available');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicles');
    }
};
