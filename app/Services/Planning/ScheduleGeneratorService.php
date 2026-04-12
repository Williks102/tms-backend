<?php

namespace App\Services\Planning;

use App\Models\Departure;
use App\Models\ScheduleTemplate;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ScheduleGeneratorService
{
    /**
     * Génère les départs pour tous les gabarits actifs sur une période.
     *
     * @return array{created: int, skipped: int, errors: int}
     */
    public function generateForPeriod(Carbon $from, Carbon $to): array
    {
        $templates = ScheduleTemplate::with('route')
            ->active()
            ->validOn($from)
            ->get();

        $result = ['created' => 0, 'skipped' => 0, 'errors' => 0];

        foreach ($templates as $template) {
            $counts = $this->generateFromTemplate($template, $from, $to);
            $result['created'] += $counts['created'];
            $result['skipped'] += $counts['skipped'];
            $result['errors']  += $counts['errors'];
        }

        return $result;
    }

    /**
     * Génère les départs d'un seul gabarit sur une période.
     *
     * @return array{created: int, skipped: int, errors: int}
     */
    public function generateFromTemplate(
        ScheduleTemplate $template,
        Carbon $from,
        Carbon $to
    ): array {
        $result  = ['created' => 0, 'skipped' => 0, 'errors' => 0];
        $current = $from->copy()->startOfDay();

        while ($current->lte($to)) {
            if ($template->isActiveOn($current)) {
                $departureAt    = $template->departureDateTimeFor($current);
                $estimatedArrival = $departureAt->copy()
                    ->addMinutes($template->route->estimated_duration_min);

                // Évite les doublons: même ligne + même datetime = ignoré
                $alreadyExists = Departure::where('route_id', $template->route_id)
                    ->where('departure_datetime', $departureAt)
                    ->exists();

                if ($alreadyExists) {
                    $result['skipped']++;
                    $current->addDay();
                    continue;
                }

                try {
                    DB::transaction(function () use ($template, $departureAt, $estimatedArrival) {
                        Departure::create([
                            'route_id'             => $template->route_id,
                            'schedule_template_id' => $template->id,
                            'departure_datetime'   => $departureAt,
                            'estimated_arrival'    => $estimatedArrival,
                            'status'               => 'scheduled',
                            // vehicle_id et driver_id seront affectés manuellement
                            'seats_available'      => 0,
                        ]);
                    });
                    $result['created']++;
                } catch (\Exception $e) {
                    Log::error('ScheduleGenerator: erreur création départ', [
                        'template_id' => $template->id,
                        'date'        => $departureAt->toDateTimeString(),
                        'error'       => $e->getMessage(),
                    ]);
                    $result['errors']++;
                }
            }

            $current->addDay();
        }

        return $result;
    }
}
