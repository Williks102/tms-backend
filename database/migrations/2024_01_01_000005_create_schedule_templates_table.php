<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schedule_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('route_id')
                  ->constrained()
                  ->cascadeOnDelete();
            $table->time('departure_time')->comment('Heure de départ récurrente, ex: 08:00:00');
            // Stocké en JSON: [0=dim, 1=lun, 2=mar, 3=mer, 4=jeu, 5=ven, 6=sam]
            // Exemple: [1,2,3,4,5] = du lundi au vendredi
            $table->json('days_of_week')->comment('Tableau des jours actifs (0=dim, 6=sam)');
            $table->date('valid_from')->comment('Date de début de validité');
            $table->date('valid_until')->nullable()->comment('null = valable indéfiniment');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['route_id', 'is_active']);
            $table->index('valid_from');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schedule_templates');
    }
};
