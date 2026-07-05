<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maintenance_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicle_id')->constrained()->cascadeOnDelete();
            $table->foreignId('maintenance_plan_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('type', ['oil_change', 'tire', 'brake', 'full_service', 'other']);
            $table->dateTime('performed_at');
            $table->decimal('mileage_at_service', 10, 2);
            $table->string('garage_name', 100);
            $table->decimal('cost_fcfa', 12, 2);
            $table->json('parts_replaced')->nullable()
                  ->comment('["filtre huile", "courroie distribution"]');
            $table->decimal('next_service_km', 10, 2)->nullable()
                  ->comment('Prochain déclencheur calculé automatiquement');
            $table->foreignId('performed_by')->constrained('users');
            $table->string('invoice_path', 255)->nullable()
                  ->comment('Chemin vers la facture scannée');
            $table->timestamps();

            $table->index(['vehicle_id', 'performed_at']);
        });
    }

    public function down(): void { Schema::dropIfExists('maintenance_records'); }
};
