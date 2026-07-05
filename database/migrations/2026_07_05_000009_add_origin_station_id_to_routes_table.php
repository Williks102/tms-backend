<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('routes', function (Blueprint $table) {
            $table->foreignId('origin_station_id')->nullable()->after('origin_city')
                  ->constrained('stations')->nullOnDelete();
        });

        // Rattache les lignes au départ d'Abidjan à la gare principale d'Adjamé.
        // Les lignes partant d'une ville sans gare seedée (ex: Bouaké) restent
        // sans gare — le gestionnaire pourra en créer une et l'assigner ensuite.
        $adjame = DB::table('stations')->where('name', "Gare d'Adjamé")->first();
        if ($adjame) {
            DB::table('routes')->where('origin_city', 'Abidjan')->update(['origin_station_id' => $adjame->id]);
        }
    }

    public function down(): void
    {
        Schema::table('routes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('origin_station_id');
        });
    }
};
