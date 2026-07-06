<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leave_requests', function (Blueprint $table) {
            $table->id();

            // Relation polymorphe — vers App\Models\User ou App\Models\Driver.
            // FQCN brut stocké (comme system_alerts.entity_type), pas de morph map.
            $table->string('employable_type');
            $table->unsignedBigInteger('employable_id');

            $table->enum('type', ['conge_paye', 'maladie', 'sans_solde', 'autre']);
            $table->date('start_date');
            $table->date('end_date');
            $table->text('reason')->nullable();

            $table->enum('status', ['pending', 'approved', 'rejected', 'cancelled'])->default('pending');
            $table->dateTime('requested_at');
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('decided_at')->nullable();
            $table->text('decision_notes')->nullable();

            $table->timestamps();

            $table->index(['employable_type', 'employable_id']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_requests');
    }
};
