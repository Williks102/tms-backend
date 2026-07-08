<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payslips', function (Blueprint $table) {
            $table->id();

            // Relation polymorphe — vers App\Models\User ou App\Models\Driver,
            // même pattern que leave_requests.employable_type/id.
            $table->string('employable_type');
            $table->unsignedBigInteger('employable_id');

            $table->string('period', 7); // "2026-07"

            // Dénormalisés — recalculés à chaque modification des lignes.
            $table->decimal('gross_amount_fcfa', 14, 2)->default(0);
            $table->decimal('deductions_amount_fcfa', 14, 2)->default(0);
            $table->decimal('net_amount_fcfa', 14, 2)->default(0);

            $table->enum('status', ['draft', 'validated', 'paid'])->default('draft');
            $table->dateTime('validated_at')->nullable();
            $table->dateTime('paid_at')->nullable();

            // Écriture de charge constatée (validation) et écriture de
            // règlement en caisse (paiement) — 2 écritures distinctes,
            // flux OHADA standard qui sépare l'engagement du décaissement.
            $table->foreignId('validation_entry_id')->nullable()->constrained('accounting_entries')->nullOnDelete();
            $table->foreignId('payment_entry_id')->nullable()->constrained('accounting_entries')->nullOnDelete();

            $table->timestamps();

            $table->index(['employable_type', 'employable_id']);
            $table->unique(['employable_type', 'employable_id', 'period']);
            $table->index('period');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payslips');
    }
};
