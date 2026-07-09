<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action'); // ex: "ticket.cancelled", "employee.created"

            // Polymorphique — FQCN brut, pas de morph map (convention déjà
            // établie partout ailleurs dans ce projet : system_alerts.entity_type,
            // leave_requests.employable_type...).
            $table->string('subject_type')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();

            $table->text('description');
            $table->json('metadata')->nullable();

            $table->timestamps();

            $table->index(['subject_type', 'subject_id']);
            $table->index('action');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};
