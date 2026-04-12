<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('routes', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique()->comment('ex: ABJ-BKE');
            $table->string('name', 100)->comment('ex: Abidjan → Bouaké');
            $table->string('origin_city', 100);
            $table->string('destination_city', 100);
            $table->decimal('distance_km', 8, 2)->comment('Pour calcul carburant théorique');
            $table->unsignedInteger('estimated_duration_min')->comment('Durée estimée en minutes');
            $table->boolean('is_dynamic')->default(false)->comment('false = fixe, true = arrêts variables');
            $table->decimal('base_fare', 10, 2)->comment('Tarif de base en FCFA');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            // Index utiles pour les requêtes fréquentes
            $table->index('is_active');
            $table->index(['origin_city', 'destination_city']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('routes');
    }
};
