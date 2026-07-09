<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('app_notifications', function (Blueprint $table) {
            $table->id();

            // Null = notification large (ex: adressée à tout un rôle plutôt
            // qu'à un utilisateur précis) — pas utilisé pour l'instant mais
            // évite une migration future si le besoin apparaît.
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();

            $table->string('type');
            $table->string('title');
            $table->text('body');
            $table->json('data')->nullable()->comment('Contexte structuré: entity_type, entity_id...');
            $table->dateTime('read_at')->nullable();

            $table->timestamps();

            $table->index(['user_id', 'read_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_notifications');
    }
};
