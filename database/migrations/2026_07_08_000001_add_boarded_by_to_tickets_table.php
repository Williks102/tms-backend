<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->foreignId('boarded_by')
                  ->nullable()
                  ->after('boarded_at')
                  ->constrained('users')
                  ->nullOnDelete()
                  ->comment('Qui a marqué le billet embarqué — scan contrôleur ou manuel manager/caissier');
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropConstrainedForeignId('boarded_by');
        });
    }
};
