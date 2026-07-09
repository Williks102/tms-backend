<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            // Nullable: null = véhicule non utilisable pour le fret (refus
            // explicite à l'enregistrement d'un colis, voir ParcelService).
            $table->decimal('cargo_capacity_kg', 8, 2)->nullable()
                  ->comment('Capacité de charge utile pour le fret (colis) — null = non utilisable pour le fret');
        });
    }

    public function down(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->dropColumn('cargo_capacity_kg');
        });
    }
};
