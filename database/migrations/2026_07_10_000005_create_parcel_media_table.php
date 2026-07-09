<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('parcel_media', function (Blueprint $table) {
            $table->id();

            $table->foreignId('parcel_id')->constrained()->cascadeOnDelete();
            $table->string('file_path');
            $table->enum('file_type', ['photo', 'document'])->default('photo');
            $table->string('description', 200)->nullable();
            $table->foreignId('uploaded_by')->constrained('users');
            $table->dateTime('uploaded_at');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('parcel_media');
    }
};
