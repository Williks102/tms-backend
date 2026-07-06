<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('disciplinary_records', function (Blueprint $table) {
            $table->id();

            $table->string('employable_type');
            $table->unsignedBigInteger('employable_id');

            $table->enum('type', ['avertissement_verbal', 'avertissement_ecrit', 'mise_a_pied', 'autre']);
            $table->text('description');
            $table->foreignId('issued_by')->constrained('users');
            $table->dateTime('issued_at');

            $table->timestamps();

            $table->index(['employable_type', 'employable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('disciplinary_records');
    }
};
