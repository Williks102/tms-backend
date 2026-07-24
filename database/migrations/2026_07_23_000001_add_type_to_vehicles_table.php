<?php
// ══════════════════════════════════════════════════════════════════════════
// Distingue les bus (transport passagers) des camions (module Fret) sur le
// même modèle Vehicle — réutilise tel quel carburant/maintenance/incidents/
// documents, déjà génériques par vehicle_id. Défaut 'bus' : rétro-compatible
// avec toute la flotte existante.
// ══════════════════════════════════════════════════════════════════════════
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->enum('type', ['bus', 'truck'])->default('bus')->after('plate_number');
        });
    }

    public function down(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->dropColumn('type');
        });
    }
};
