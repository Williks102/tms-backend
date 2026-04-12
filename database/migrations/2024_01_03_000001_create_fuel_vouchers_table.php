<?php
// ══════════════════════════════════════════════════════════════════════════
// 2024_01_03_000001_create_fuel_vouchers_table.php
// ══════════════════════════════════════════════════════════════════════════
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fuel_vouchers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('departure_id')->constrained()->cascadeOnDelete();
            $table->foreignId('driver_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vehicle_id')->constrained()->cascadeOnDelete();
            $table->decimal('requested_liters', 6, 2);
            $table->decimal('theoretical_liters', 6, 2)->default(0)
                  ->comment('Calculé automatiquement à la soumission');
            $table->decimal('approved_liters', 6, 2)->nullable();
            $table->enum('fuel_type', ['gasoil', 'essence', 'gpl'])->default('gasoil');
            $table->decimal('price_per_liter', 6, 2)->default(0)
                  ->comment('Prix au moment de la demande');
            $table->decimal('total_cost', 10, 2)->nullable()
                  ->comment('Calculé à l\'approbation: approved_liters × price_per_liter');
            $table->string('station_name', 100)->nullable();
            $table->enum('status', [
                'pending',   // En attente de validation gestionnaire
                'approved',  // Approuvé
                'rejected',  // Refusé
                'consumed',  // Carburant effectivement pris
            ])->default('pending');
            $table->text('rejection_reason')->nullable();
            $table->dateTime('requested_at');
            $table->dateTime('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'requested_at']);
            $table->index('vehicle_id');
            $table->index('driver_id');
        });
    }

    public function down(): void { Schema::dropIfExists('fuel_vouchers'); }
};
