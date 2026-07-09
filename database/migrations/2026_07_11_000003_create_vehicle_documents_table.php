<?php
// ══════════════════════════════════════════════════════════════════════════
// 2026_07_11_000003_create_vehicle_documents_table.php
// Copie exacte du pattern driver_documents (2024_01_02_000004).
// ══════════════════════════════════════════════════════════════════════════
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicle_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicle_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['assurance', 'controle_technique', 'vignette', 'other']);
            $table->string('file_path', 255);
            $table->date('expires_at')->nullable();
            $table->boolean('is_verified')->default(false)->comment('Vérifié par le manager');
            $table->dateTime('uploaded_at');
            $table->timestamps();

            $table->index(['vehicle_id', 'type']);
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicle_documents');
    }
};
