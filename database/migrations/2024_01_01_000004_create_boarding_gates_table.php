<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('boarding_gates', function (Blueprint $table) {
            $table->id();
            $table->string('station_name', 100)->comment('ex: Gare de Yopougon');
            $table->string('gate_code', 10)->comment('ex: Q1, Q2, Q3...');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // Un code de quai est unique par gare
            $table->unique(['station_name', 'gate_code']);
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('boarding_gates');
    }
};
