<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone', 30)->nullable()->after('email');
            $table->date('hired_at')->nullable()->after('phone');
            $table->enum('contract_type', ['cdi', 'cdd', 'stage', 'interim', 'autre'])->nullable()->after('hired_at');
            $table->date('contract_end_date')->nullable()->comment('Pertinent pour CDD/stage/intérim')->after('contract_type');
            $table->decimal('base_salary_fcfa', 10, 2)->nullable()->after('contract_end_date');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['phone', 'hired_at', 'contract_type', 'contract_end_date', 'base_salary_fcfa']);
        });
    }
};
