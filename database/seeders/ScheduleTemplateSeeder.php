<?php
// ══════════════════════════════════════════════════════════════════════════
// database/seeders/ScheduleTemplateSeeder.php
// ══════════════════════════════════════════════════════════════════════════
namespace Database\Seeders;

use App\Models\Route;
use App\Models\ScheduleTemplate;
use Illuminate\Database\Seeder;

class ScheduleTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $templates = [
            // ABJ → BKE : 3 départs par jour, lun-sam
            ['route_code' => 'ABJ-BKE', 'departure_time' => '06:00', 'days' => [1,2,3,4,5,6]],
            ['route_code' => 'ABJ-BKE', 'departure_time' => '10:00', 'days' => [1,2,3,4,5,6]],
            ['route_code' => 'ABJ-BKE', 'departure_time' => '14:00', 'days' => [1,2,3,4,5,6]],

            // ABJ → YAM : 2 départs par jour, tous les jours
            ['route_code' => 'ABJ-YAM', 'departure_time' => '07:00', 'days' => [0,1,2,3,4,5,6]],
            ['route_code' => 'ABJ-YAM', 'departure_time' => '13:00', 'days' => [0,1,2,3,4,5,6]],

            // ABJ → SAN : 2 départs par jour, lun-sam
            ['route_code' => 'ABJ-SAN', 'departure_time' => '06:30', 'days' => [1,2,3,4,5,6]],
            ['route_code' => 'ABJ-SAN', 'departure_time' => '11:00', 'days' => [1,2,3,4,5,6]],

            // ABJ → DAL : 1 départ par jour, lun-ven
            ['route_code' => 'ABJ-DAL', 'departure_time' => '07:30', 'days' => [1,2,3,4,5]],

            // ABJ → MAN : 1 départ par jour, lun-ven
            ['route_code' => 'ABJ-MAN', 'departure_time' => '05:30', 'days' => [1,2,3,4,5]],

            // BKE → ABJ : 2 départs par jour, lun-sam
            ['route_code' => 'BKE-ABJ', 'departure_time' => '05:30', 'days' => [1,2,3,4,5,6]],
            ['route_code' => 'BKE-ABJ', 'departure_time' => '13:00', 'days' => [1,2,3,4,5,6]],

            // ABJ → KOR : 1 départ, lun-ven
            ['route_code' => 'ABJ-KOR', 'departure_time' => '05:00', 'days' => [1,2,3,4,5]],
        ];

        $count = 0;
        foreach ($templates as $tpl) {
            $route = Route::where('code', $tpl['route_code'])->first();
            if (!$route) continue;

            ScheduleTemplate::firstOrCreate(
                [
                    'route_id'       => $route->id,
                    'departure_time' => $tpl['departure_time'],
                ],
                [
                    'route_id'       => $route->id,
                    'departure_time' => $tpl['departure_time'],
                    'days_of_week'   => $tpl['days'],
                    'valid_from'     => '2024-01-01',
                    'valid_until'    => null,
                    'is_active'      => true,
                ]
            );
            $count++;
        }

        $this->command->info("  Gabarits horaires créés: {$count}");
    }
}


