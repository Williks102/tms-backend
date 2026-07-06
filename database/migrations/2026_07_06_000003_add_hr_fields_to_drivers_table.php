<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('drivers', function (Blueprint $table) {
            $table->enum('contract_type', ['cdi', 'cdd', 'stage', 'interim', 'autre'])->nullable()->after('hired_at');
            $table->date('contract_end_date')->nullable()->comment('Pertinent pour CDD/stage/intérim')->after('contract_type');
            $table->decimal('base_salary_fcfa', 10, 2)->nullable()->after('contract_end_date');
        });
    }

    public function down(): void
    {
        Schema::table('drivers', function (Blueprint $table) {
            $table->dropColumn(['contract_type', 'contract_end_date', 'base_salary_fcfa']);
        });
    }
};
