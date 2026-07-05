<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('incident_media', function (Blueprint $table) {
            $table->id();
            $table->foreignId('incident_id')
                  ->constrained()->cascadeOnDelete();
            $table->string('file_path', 255);
            $table->enum('file_type', [
                'photo',          // Photo de l'incident
                'video',          // Vidéo
                'document',       // Document administratif
                'police_report',  // Rapport de police
            ]);
            $table->string('description', 200)->nullable();
            $table->foreignId('uploaded_by')->constrained('users');
            $table->dateTime('uploaded_at');
            $table->timestamps();

            $table->index('incident_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incident_media');
    }
};
