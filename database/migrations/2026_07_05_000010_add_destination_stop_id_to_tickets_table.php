<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->foreignId('destination_stop_id')->nullable()->after('departure_id')
                  ->constrained('route_stops')->nullOnDelete()
                  ->comment('Null = destination finale de la ligne. Sinon, arrêt intermédiaire où le passager descend.');
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropConstrainedForeignId('destination_stop_id');
        });
    }
};
