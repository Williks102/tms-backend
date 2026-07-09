<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('departures', function (Blueprint $table) {
            // Snapshot depuis vehicle.cargo_capacity_kg lors de l'affectation
            // du véhicule — même pattern que seats_available.
            $table->decimal('cargo_capacity_kg', 8, 2)->nullable()
                  ->comment('Copié depuis vehicle.cargo_capacity_kg lors de l\'affectation');
            $table->decimal('cargo_used_kg', 8, 2)->default(0)
                  ->comment('Somme des poids des colis enregistrés sur ce départ');
        });
    }

    public function down(): void
    {
        Schema::table('departures', function (Blueprint $table) {
            $table->dropColumn(['cargo_capacity_kg', 'cargo_used_kg']);
        });
    }
};
