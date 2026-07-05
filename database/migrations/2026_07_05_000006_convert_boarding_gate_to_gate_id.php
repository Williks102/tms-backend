<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('departures', function (Blueprint $table) {
            $table->foreignId('boarding_gate_id')->nullable()->after('boarding_gate')
                  ->constrained('boarding_gates')->nullOnDelete();
        });

        // Backfill depuis l'ancien code texte. Best-effort: si plusieurs gares
        // partagent le même gate_code, on prend la première correspondance
        // (ambiguïté déjà présente dans les données, pas introduite ici — données de dev).
        DB::table('departures')->whereNotNull('boarding_gate')->get()->each(function ($departure) {
            $gate = DB::table('boarding_gates')->where('gate_code', $departure->boarding_gate)->first();
            if ($gate) {
                DB::table('departures')->where('id', $departure->id)->update(['boarding_gate_id' => $gate->id]);
            }
        });

        Schema::table('departures', function (Blueprint $table) {
            $table->dropColumn('boarding_gate');
        });
    }

    public function down(): void
    {
        Schema::table('departures', function (Blueprint $table) {
            $table->string('boarding_gate', 10)->nullable()->after('boarding_gate_id');
        });

        DB::table('departures')->whereNotNull('boarding_gate_id')->get()->each(function ($departure) {
            $gate = DB::table('boarding_gates')->where('id', $departure->boarding_gate_id)->first();
            if ($gate) {
                DB::table('departures')->where('id', $departure->id)->update(['boarding_gate' => $gate->gate_code]);
            }
        });

        Schema::table('departures', function (Blueprint $table) {
            $table->dropConstrainedForeignId('boarding_gate_id');
        });
    }
};
