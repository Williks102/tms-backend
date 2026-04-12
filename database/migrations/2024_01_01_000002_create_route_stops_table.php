<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('route_stops', function (Blueprint $table) {
            $table->id();
            $table->foreignId('route_id')
                  ->constrained()
                  ->cascadeOnDelete()
                  ->comment('Uniquement pour les lignes is_dynamic = true');
            $table->string('city_name', 100);
            $table->unsignedInteger('stop_order')->comment('Ordre sur l\'itinéraire, commence à 1');
            $table->decimal('distance_from_origin_km', 8, 2);
            $table->decimal('fare_from_origin', 10, 2)->comment('Tarif partiel depuis l\'origine');
            $table->timestamps();

            // Un arrêt ne peut apparaître qu'une fois par ligne et dans un seul ordre
            $table->unique(['route_id', 'stop_order']);
            $table->unique(['route_id', 'city_name']);

            $table->index('route_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('route_stops');
    }
};
