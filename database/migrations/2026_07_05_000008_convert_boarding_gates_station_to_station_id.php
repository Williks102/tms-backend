<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('boarding_gates', function (Blueprint $table) {
            $table->foreignId('station_id')->nullable()->after('id')
                  ->constrained('stations')->cascadeOnDelete();
        });

        DB::table('boarding_gates')->get()->each(function ($gate) {
            $station = DB::table('stations')->where('name', $gate->station_name)->first();
            if ($station) {
                DB::table('boarding_gates')->where('id', $gate->id)->update(['station_id' => $station->id]);
            }
        });

        Schema::table('boarding_gates', function (Blueprint $table) {
            $table->unsignedBigInteger('station_id')->nullable(false)->change();
            $table->dropUnique(['station_name', 'gate_code']);
            $table->dropColumn('station_name');
            $table->unique(['station_id', 'gate_code']);
        });
    }

    public function down(): void
    {
        Schema::table('boarding_gates', function (Blueprint $table) {
            $table->string('station_name', 100)->nullable()->after('station_id');
        });

        DB::table('boarding_gates')->get()->each(function ($gate) {
            $station = DB::table('stations')->where('id', $gate->station_id)->first();
            if ($station) {
                DB::table('boarding_gates')->where('id', $gate->id)->update(['station_name' => $station->name]);
            }
        });

        Schema::table('boarding_gates', function (Blueprint $table) {
            $table->string('station_name', 100)->nullable(false)->change();
            $table->dropUnique(['station_id', 'gate_code']);
            $table->unique(['station_name', 'gate_code']);
            $table->dropConstrainedForeignId('station_id');
        });
    }
};
