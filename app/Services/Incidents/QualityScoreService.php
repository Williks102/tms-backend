<?php
// ══════════════════════════════════════════════════════════════════════════
// app/Services/Incidents/QualityScoreService.php
// ══════════════════════════════════════════════════════════════════════════
namespace App\Services\Incidents;

use App\Models\Incident;
use App\Models\IncidentQualityScore;
use Carbon\Carbon;

class QualityScoreService
{
    // Poids des déductions par sévérité
    private const DEDUCTIONS = [
        'critical' => 40,
        'high'     => 20,
        'medium'   => 10,
        'low'      => 5,
    ];

    /**
     * Calcule le score qualité d'un chauffeur pour un mois donné.
     * Score 100 = aucun incident. Plancher à 0.
     */
    public function calculateForDriver(int $driverId, Carbon $month): float
    {
        return $this->calculate('driver', $driverId, $month);
    }

    public function calculateForVehicle(int $vehicleId, Carbon $month): float
    {
        return $this->calculate('vehicle', $vehicleId, $month);
    }

    public function calculateForRoute(int $routeId, Carbon $month): float
    {
        $start = $month->copy()->startOfMonth();
        $end   = $month->copy()->endOfMonth();

        $incidents = Incident::whereHas('departure', fn($q) => $q->where('route_id', $routeId))
            ->whereBetween('occurred_at', [$start, $end])
            ->get();

        return $this->computeScore('route', $routeId, $incidents, $start);
    }

    private function calculate(string $entityType, int $entityId, Carbon $month): float
    {
        $start = $month->copy()->startOfMonth();
        $end   = $month->copy()->endOfMonth();

        $field     = $entityType . '_id';
        $incidents = Incident::where($field, $entityId)
            ->whereBetween('occurred_at', [$start, $end])
            ->get();

        return $this->computeScore($entityType, $entityId, $incidents, $start);
    }

    private function computeScore(
        string $entityType,
        int    $entityId,
        \Illuminate\Support\Collection $incidents,
        Carbon $monthStart
    ): float {
        if ($incidents->isEmpty()) {
            $this->saveScore($entityType, $entityId, $monthStart, [], 100.0);
            return 100.0;
        }

        $deductions = 0;

        foreach ($incidents as $incident) {
            $deductions += self::DEDUCTIONS[$incident->severity] ?? 0;

            // Malus si ouvert depuis plus de 24h
            if ($incident->isOpen() && now()->diffInHours($incident->occurred_at) > 24) {
                $deductions += 5;
            }
        }

        $avgResolutionHours = $incidents
            ->filter(fn($i) => $i->resolved_at !== null)
            ->avg(fn($i) => $i->resolutionTimeHours()) ?? null;

        $score = max(0.0, 100.0 - $deductions);

        $this->saveScore($entityType, $entityId, $monthStart, [
            'incidents'      => $incidents,
            'avg_resolution' => $avgResolutionHours,
        ], $score);

        return round($score, 2);
    }

    private function saveScore(
        string $entityType,
        int    $entityId,
        Carbon $monthStart,
        array  $data,
        float  $score
    ): void {
        $incidents = $data['incidents'] ?? collect();

        IncidentQualityScore::updateOrCreate(
            ['entity_type' => $entityType, 'entity_id' => $entityId, 'month' => $monthStart->toDateString()],
            [
                'incidents_count'        => $incidents->count(),
                'critical_count'         => $incidents->where('severity', 'critical')->count(),
                'high_count'             => $incidents->where('severity', 'high')->count(),
                'total_financial_impact' => $incidents->sum('financial_impact_fcfa'),
                'quality_score'          => $score,
                'avg_resolution_hours'   => $data['avg_resolution'] ?? null,
            ]
        );
    }

    /**
     * Score global chauffeur = éco-score (60%) + qualité incidents (40%)
     */
    public function globalDriverScore(int $driverId, Carbon $month): float
    {
        $ecoScore     = \App\Models\DriverMonthlyScore::where('driver_id', $driverId)
            ->where('month', $month->copy()->startOfMonth()->toDateString())
            ->value('avg_eco_score') ?? 100;

        $qualityScore = $this->calculateForDriver($driverId, $month);

        return round(($ecoScore * 0.6) + ($qualityScore * 0.4), 2);
    }
}
