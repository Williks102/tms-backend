<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            // Distinctes de vehicle_documents.expires_at (fichiers uploadés) —
            // même pattern que drivers.license_expires_at/medical_expires_at :
            // sans ces colonnes dédiées, un ancien document expiré resterait
            // en base après renouvellement et bloquerait le véhicule à tort.
            $table->date('insurance_expires_at')->nullable();
            $table->date('controle_technique_expires_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->dropColumn(['insurance_expires_at', 'controle_technique_expires_at']);
        });
    }
};
