<?php
// ══════════════════════════════════════════════════════════════════════════
// app/Jobs/Drivers/AggregateMonthlyScores.php
// Planifié dans Console/Kernel.php: $schedule->job(...)->monthlyOn(1, '01:00')
// ══════════════════════════════════════════════════════════════════════════
namespace App\Jobs\Drivers;

use App\Models\Driver;
use App\Models\DriverMonthlyScore;
use App\Models\DriverTripStats;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class AggregateMonthlyScores implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        private readonly Carbon $month // Le mois à agréger (toujours le 1er du mois)
    ) {}

    public function handle(): void
    {
        $monthStart = $this->month->copy()->startOfMonth();
        $monthEnd   = $this->month->copy()->endOfMonth();

        Log::info("AggregateMonthlyScores: démarrage pour {$monthStart->format('Y-m')}");

        $drivers = Driver::withTrashed()->get(); // Inclut les soft-deleted
        $scores  = [];

        foreach ($drivers as $driver) {
            $stats = DriverTripStats::where('driver_id', $driver->id)
                ->whereBetween('recorded_at', [$monthStart, $monthEnd])
                ->get();

            if ($stats->isEmpty()) continue;

            $fuelSavings = $stats->sum(function ($s) {
                return $s->fuel_theoretical_liters - $s->fuel_consumed_liters;
            });

            $scores[] = [
                'driver_id'              => $driver->id,
                'month'                  => $monthStart->toDateString(),
                'trips_count'            => $stats->count(),
                'total_distance_km'      => round($stats->sum('distance_km'), 2),
                'avg_eco_score'          => round($stats->whereNotNull('eco_score')->avg('eco_score') ?? 0, 2),
                'fuel_savings_liters'    => round($fuelSavings, 2),
                'total_overspeed_events' => $stats->sum('overspeed_events'),
                'avg_delay_min'          => round($stats->avg('delay_min'), 2),
            ];
        }

        if (empty($scores)) {
            Log::info('AggregateMonthlyScores: aucun voyage ce mois');
            return;
        }

        // Classement par éco-score décroissant
        usort($scores, fn($a, $b) => $b['avg_eco_score'] <=> $a['avg_eco_score']);

        foreach ($scores as $rank => $data) {
            DriverMonthlyScore::updateOrCreate(
                [
                    'driver_id' => $data['driver_id'],
                    'month'     => $data['month'],
                ],
                array_merge($data, ['rank' => $rank + 1])
            );
        }

        Log::info("AggregateMonthlyScores: {$monthStart->format('Y-m')} — ".($rank + 1)." chauffeurs classés");
    }
}