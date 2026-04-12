<?php
// ══════════════════════════════════════════════════════════════════════════
// app/Services/Drivers/EcoScoreService.php
// ══════════════════════════════════════════════════════════════════════════
namespace App\Services\Drivers;

use App\Models\DriverTripStats;

class EcoScoreService
{
    /**
     * Calcule et sauvegarde l'éco-score pour un voyage donné.
     *
     * Score sur 100 points:
     *  - 40 pts : consommation carburant (réel vs théorique)
     *  - 30 pts : excès de vitesse
     *  - 30 pts : ponctualité
     */
    public function calculate(DriverTripStats $stats): float
    {
        // ── Composante 1: Carburant (40 pts) ──────────────────────────
        // ratio = 1.0 → 40pts | ratio = 1.5 → 20pts | ratio ≥ 2.0 → 0pts
        $fuelRatio  = $stats->fuel_consumed_liters
            / max($stats->fuel_theoretical_liters, 0.1);
        $fuelScore  = max(0.0, 40.0 * (2.0 - $fuelRatio));

        // ── Composante 2: Excès de vitesse (30 pts) ───────────────────
        // Chaque événement coûte 5 points, plancher à 0
        $speedScore = max(0.0, 30.0 - ($stats->overspeed_events * 5.0));

        // ── Composante 3: Ponctualité (30 pts) ────────────────────────
        // 1 point perdu par minute de retard, plancher à 0
        // delay_min négatif (en avance) = pas de bonus au-delà de 30
        $delayScore = max(0.0, 30.0 - max(0, $stats->delay_min));

        $total = round($fuelScore + $speedScore + $delayScore, 2);

        $stats->update(['eco_score' => $total]);

        return $total;
    }

    /**
     * Calcule le score moyen d'un chauffeur sur N derniers voyages.
     */
    public function recentAverage(int $driverId, int $lastN = 10): ?float
    {
        $avg = DriverTripStats::where('driver_id', $driverId)
            ->whereNotNull('eco_score')
            ->latest('recorded_at')
            ->limit($lastN)
            ->avg('eco_score');

        return $avg ? round($avg, 2) : null;
    }

    /**
     * Détermine le niveau de performance à partir d'un score.
     */
    public function getLevel(float $score): string
    {
        return match (true) {
            $score >= 90 => 'excellent',
            $score >= 75 => 'good',
            $score >= 50 => 'average',
            default      => 'poor',
        };
    }
}
