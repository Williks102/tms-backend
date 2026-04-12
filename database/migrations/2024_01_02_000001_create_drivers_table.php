<?php
// ══════════════════════════════════════════════════════════════════════════
// 2024_01_02_000001_create_drivers_table.php
// ══════════════════════════════════════════════════════════════════════════
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('drivers', function (Blueprint $table) {
            $table->id();
            $table->string('employee_number', 20)->unique()->comment('ex: CH-2024-001');
            $table->string('first_name', 100);
            $table->string('last_name', 100);
            $table->string('phone', 20);
            $table->string('license_number', 50)->unique();
            $table->string('license_category', 10)->comment('ex: D, D+E');
            $table->date('license_expires_at');
            $table->date('medical_expires_at')->comment('Visite médicale obligatoire');
            $table->date('hired_at');
            $table->enum('status', [
                'available',   // Disponible pour affectation
                'on_duty',     // En service (en voyage)
                'resting',     // En période de repos obligatoire
                'on_leave',    // En congé
                'suspended',   // Suspendu temporairement
            ])->default('available');
            $table->foreignId('user_id')
                  ->nullable()
                  ->constrained()
                  ->nullOnDelete()
                  ->comment('Compte app mobile du chauffeur');
            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
            $table->index('license_expires_at');
            $table->index('medical_expires_at');
        });

        // Maintenant qu'on a la table drivers, on peut ajouter la FK sur departures
        Schema::table('departures', function (Blueprint $table) {
            $table->foreign('driver_id')
                  ->references('id')
                  ->on('drivers')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('departures', function (Blueprint $table) {
            $table->dropForeign(['driver_id']);
        });
        Schema::dropIfExists('drivers');
    }
};
