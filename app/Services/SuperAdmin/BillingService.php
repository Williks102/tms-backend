<?php
// ══════════════════════════════════════════════════════════════════════════
// app/Services/SuperAdmin/BillingService.php
//
// Logique partagée entre les commandes CLI (tms:billing-report,
// tms:subscription) et SuperAdminController — un seul endroit pour les
// tarifs et le calcul du rapport mensuel. Voir CLAUDE.md § Revente SaaS —
// statut d'abonnement et facturation.
//
// ⚠️ Tarifs et paliers ILLUSTRATIFS (21/07/2026), jamais validés par de
// vrais prospects — même statut que PayrollTaxBracketSeeder pour le barème
// CNPS/ITS. À ajuster ici dès qu'un vrai modèle est confirmé — un seul
// endroit à changer, CLI et web en dépendent tous les deux.
// ══════════════════════════════════════════════════════════════════════════
namespace App\Services\SuperAdmin;

use App\Models\SubscriptionStatus;
use App\Models\Ticket;
use App\Models\Vehicle;
use Illuminate\Support\Carbon;

class BillingService
{
    private const TIER_SMALL_MAX_VEHICLES  = 10;
    private const TIER_MEDIUM_MAX_VEHICLES = 30;

    private const TIER_SMALL_FCFA  = 75_000;
    private const TIER_MEDIUM_FCFA = 150_000;
    private const TIER_LARGE_FCFA  = 250_000;

    public const COMMISSION_PER_ONLINE_TICKET_FCFA = 75;

    public const SETUP_FEE_RANGE_FCFA = '150 000–250 000';

    /** @return array<int, array{label: string, max_vehicles: int|null, monthly_fcfa: int}> */
    public function tierGrid(): array
    {
        return [
            ['label' => 'Palier 1 (≤10 véhicules)',   'max_vehicles' => self::TIER_SMALL_MAX_VEHICLES,  'monthly_fcfa' => self::TIER_SMALL_FCFA],
            ['label' => 'Palier 2 (11-30 véhicules)',  'max_vehicles' => self::TIER_MEDIUM_MAX_VEHICLES, 'monthly_fcfa' => self::TIER_MEDIUM_FCFA],
            ['label' => 'Palier 3 (31+ véhicules)',    'max_vehicles' => null,                            'monthly_fcfa' => self::TIER_LARGE_FCFA],
        ];
    }

    /** @return array{label: string, monthly_fcfa: int} */
    public function tierFor(int $fleetSize): array
    {
        if ($fleetSize <= self::TIER_SMALL_MAX_VEHICLES) {
            return ['label' => '1 (≤10 véhicules)', 'monthly_fcfa' => self::TIER_SMALL_FCFA];
        }

        if ($fleetSize <= self::TIER_MEDIUM_MAX_VEHICLES) {
            return ['label' => '2 (11-30 véhicules)', 'monthly_fcfa' => self::TIER_MEDIUM_FCFA];
        }

        return ['label' => '3 (31+ véhicules)', 'monthly_fcfa' => self::TIER_LARGE_FCFA];
    }

    public function monthlyReport(?string $month = null): array
    {
        $start = $month
            ? Carbon::createFromFormat('Y-m', $month)->startOfMonth()
            : now()->startOfMonth();
        $end = $start->copy()->endOfMonth();

        // Vehicle utilise SoftDeletes — count() exclut déjà les véhicules
        // désaffectés (scope global Eloquent), pas de filtre explicite requis.
        $fleetSize = Vehicle::count();
        $tier      = $this->tierFor($fleetSize);

        $onlineQuery = Ticket::where('channel', 'online')
            ->where('status', 'paid')
            ->whereBetween('purchased_at', [$start, $end]);

        $ticketsCount   = (clone $onlineQuery)->count();
        $volumeFcfa     = (float) (clone $onlineQuery)->sum('price_fcfa');
        $commissionFcfa = $ticketsCount * self::COMMISSION_PER_ONLINE_TICKET_FCFA;

        return [
            'month'                      => $start->format('Y-m'),
            'month_label'                => $start->translatedFormat('F Y'),
            'fleet_size'                 => $fleetSize,
            'tier'                       => $tier,
            'online_tickets_count'       => $ticketsCount,
            'online_volume_fcfa'         => $volumeFcfa,
            'commission_per_ticket_fcfa' => self::COMMISSION_PER_ONLINE_TICKET_FCFA,
            'commission_fcfa'            => $commissionFcfa,
            'total_fcfa'                 => $tier['monthly_fcfa'] + $commissionFcfa,
        ];
    }

    public function currentSubscription(): SubscriptionStatus
    {
        return SubscriptionStatus::current();
    }

    public function updateSubscription(string $status, ?string $paidUntil = null, ?string $note = null): SubscriptionStatus
    {
        $subscription = SubscriptionStatus::current();
        $subscription->status = $status;

        if ($paidUntil !== null) {
            $subscription->paid_until = $paidUntil;
        }

        if ($note !== null) {
            $subscription->note = $note;
        }

        $subscription->save();

        return $subscription;
    }
}
