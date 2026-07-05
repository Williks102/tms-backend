<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stations', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique()->comment('ex: Gare d\'Adjamé');
            $table->string('city', 100)->comment('ex: Abidjan');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('city');
        });

        // Crée une gare pour chaque station_name déjà utilisé par les quais existants.
        $existingStationNames = DB::table('boarding_gates')->distinct()->pluck('station_name');
        foreach ($existingStationNames as $name) {
            DB::table('stations')->insert([
                'name'       => $name,
                'city'       => 'Abidjan', // Les gares seedées (Adjamé, Yopougon) sont toutes à Abidjan
                'is_active'  => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('stations');
    }
};
