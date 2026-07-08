<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_vouchers', function (Blueprint $table) {
            $table->id();

            // BSC-{year}-{seq} (sortie) ou BEC-{year}-{seq} (entrée)
            $table->string('reference')->unique();
            $table->enum('type', ['sortie', 'entree']);
            $table->decimal('amount_fcfa', 14, 2);

            // Compte de contrepartie (ex. 6052 carburant, 622 entretien, 401
            // fournisseurs pour une sortie ; 706/758 pour une entrée hors vente).
            $table->foreignId('account_id')->constrained('accounting_accounts');

            $table->string('label');
            $table->string('beneficiary_name')->nullable();

            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->foreignId('requested_by')->constrained('users');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('approved_at')->nullable();
            $table->text('rejection_reason')->nullable();

            $table->foreignId('accounting_entry_id')->nullable()->constrained('accounting_entries')->nullOnDelete();

            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_vouchers');
    }
};
