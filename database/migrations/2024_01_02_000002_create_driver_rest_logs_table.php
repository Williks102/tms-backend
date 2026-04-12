<?php
// ══════════════════════════════════════════════════════════════════════════
// 2024_01_02_000002_create_driver_rest_logs_table.php
// ══════════════════════════════════════════════════════════════════════════
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('driver_rest_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('driver_id')->constrained()->cascadeOnDelete();
            $table->foreignId('departure_id')->constrained()->cascadeOnDelete();
            $table->dateTime('duty_start')->comment('Heure de prise de service');
            $table->dateTime('duty_end')->nullable()->comment('Heure de fin de service');
            $table->unsignedInteger('duty_duration_min')->nullable()->comment('Calculé automatiquement');
            $table->dateTime('rest_start')->nullable();
            $table->dateTime('rest_end')->nullable();
            $table->unsignedInteger('rest_duration_min')->nullable();
            $table->boolean('is_compliant')->default(true)
                  ->comment('Repos suffisant avant ce départ');
            $table->timestamps();

            $table->index(['driver_id', 'rest_end']);
            $table->index('departure_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('driver_rest_logs');
    }
};
