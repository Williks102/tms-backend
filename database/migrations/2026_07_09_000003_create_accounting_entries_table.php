<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounting_entries', function (Blueprint $table) {
            $table->id();

            $table->foreignId('journal_id')->constrained('accounting_journals');
            $table->string('reference')->unique(); // {code journal}-{year}-{seq:06d}
            $table->date('entry_date');
            $table->string('label');

            // Relation polymorphe vers l'événement source (Ticket, FuelVoucher,
            // MaintenanceRecord, CashVoucher, Payslip...) — null si saisie
            // manuelle (opération diverse). FQCN brut, pas de morph map, comme
            // system_alerts.entity_type / leave_requests.employable_type.
            $table->string('source_type')->nullable();
            $table->unsignedBigInteger('source_id')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index(['source_type', 'source_id']);
            $table->index('entry_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_entries');
    }
};
