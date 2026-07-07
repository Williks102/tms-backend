<?php

namespace App\Services\Planning;

use App\Models\Departure;
use App\Models\Station;
use Carbon\Carbon;

class BoardService
{
    /**
     * Départs/arrivées du jour pour l'écran public de gare.
     * Sans $stationId : toutes gares confondues.
     */
    public function forStation(?int $stationId): array
    {
        $station = $stationId ? Station::find($stationId) : null;

        $departures = Departure::with(['route', 'vehicle', 'gate'])
            ->notCancelled()
            ->forDate(Carbon::today())
            ->whereIn('status', ['scheduled', 'boarding', 'departed'])
            ->when($station, fn ($query) => $query->whereHas(
                'route',
                fn ($q) => $q->where('origin_station_id', $station->id)
            ))
            ->orderBy('departure_datetime')
            ->get();

        $arrivals = Departure::with(['route', 'vehicle', 'gate'])
            ->notCancelled()
            ->forDate(Carbon::today())
            ->whereIn('status', ['departed', 'arrived'])
            ->when($station, fn ($query) => $query->whereHas(
                'route',
                fn ($q) => $q->where('destination_city', $station->city)
            ))
            ->orderBy('estimated_arrival')
            ->get();

        return [
            'station'    => $station,
            'departures' => $departures,
            'arrivals'   => $arrivals,
        ];
    }
}
