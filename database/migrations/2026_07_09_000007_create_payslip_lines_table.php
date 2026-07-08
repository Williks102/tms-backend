<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payslip_lines', function (Blueprint $table) {
            $table->id();

            $table->foreignId('payslip_id')->constrained('payslips')->cascadeOnDelete();
            $table->enum('type', ['gain', 'retenue']);
            $table->string('label');
            $table->decimal('amount_fcfa', 14, 2);

            // Compte de contrepartie pour une ligne "retenue" (ex. 431 CNPS,
            // 447 État ITS). Nullable — si absent, la retenue réduit
            // simplement le net à payer via 421 Personnel (voir PayrollService).
            $table->foreignId('account_id')->nullable()->constrained('accounting_accounts')->nullOnDelete();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payslip_lines');
    }
};
