<?php
// ══════════════════════════════════════════════════════════════════════════
// app/Console/Commands/BillingReportCommand.php
//
// Rapport de facturation SaaS — usage interne au porteur du projet
// uniquement, jamais exposé via l'API/UI (le personnel d'une compagnie
// cliente n'a pas à voir combien elle doit à son fournisseur SaaS). Chaque
// compagnie cliente a son propre déploiement isolé (voir
// runbook-nouveau-client.md, option B), donc ce rapport se lance par
// déploiement, via la console Railway du client concerné :
//
//   php artisan tms:billing-report [--month=AAAA-MM]
//
// ⚠️ Tarifs et palier de flotte ILLUSTRATIFS (conversation du 21/07/2026,
// jamais validés par de vrais prospects) — même statut que
// PayrollTaxBracketSeeder pour le barème CNPS/ITS : un point de départ
// raisonné, à ajuster ici dès qu'un vrai modèle de prix est confirmé.
// ══════════════════════════════════════════════════════════════════════════
namespace App\Console\Commands;

use App\Models\Ticket;
use App\Models\Vehicle;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class BillingReportCommand extends Command
{
    protected $signature = 'tms:billing-report {--month= : Mois au format AAAA-MM, défaut = mois en cours}';

    protected $description = 'Rapport de facturation SaaS (usage vendeur uniquement) — taille de flotte et volume de billets en ligne du mois';

    private const TIER_SMALL_MAX_VEHICLES  = 10;
    private const TIER_MEDIUM_MAX_VEHICLES = 30;

    private const TIER_SMALL_FCFA  = 75_000;
    private const TIER_MEDIUM_FCFA = 150_000;
    private const TIER_LARGE_FCFA  = 250_000;

    private const COMMISSION_PER_ONLINE_TICKET_FCFA = 75;

    public function handle(): int
    {
        $month = $this->option('month')
            ? Carbon::createFromFormat('Y-m', $this->option('month'))->startOfMonth()
            : now()->startOfMonth();
        $end = $month->copy()->endOfMonth();

        // Vehicle utilise SoftDeletes — count() exclut déjà les véhicules
        // désaffectés (scope global Eloquent), pas de filtre explicite requis.
        $fleetSize = Vehicle::count();
        [$tierLabel, $subscriptionFcfa] = $this->tierFor($fleetSize);

        $onlineTicketsQuery = Ticket::where('channel', 'online')
            ->where('status', 'paid')
            ->whereBetween('purchased_at', [$month, $end]);

        $onlineTicketsCount = (clone $onlineTicketsQuery)->count();
        $onlineVolumeFcfa   = (clone $onlineTicketsQuery)->sum('price_fcfa');
        $commissionFcfa     = $onlineTicketsCount * self::COMMISSION_PER_ONLINE_TICKET_FCFA;

        $this->info("Rapport de facturation — {$month->translatedFormat('F Y')}");
        $this->newLine();
        $this->line("Taille de flotte actuelle  : {$fleetSize} véhicule(s) → palier {$tierLabel}");
        $this->line('Abonnement mensuel         : '.number_format($subscriptionFcfa, 0, ',', ' ').' FCFA');
        $this->newLine();
        $this->line("Billets en ligne vendus    : {$onlineTicketsCount}");
        $this->line('Volume billets en ligne    : '.number_format((float) $onlineVolumeFcfa, 0, ',', ' ').' FCFA');
        $this->line('Commission ('.self::COMMISSION_PER_ONLINE_TICKET_FCFA.' FCFA/billet) : '.number_format($commissionFcfa, 0, ',', ' ').' FCFA');
        $this->newLine();
        $this->info('Total à facturer ce mois   : '.number_format($subscriptionFcfa + $commissionFcfa, 0, ',', ' ').' FCFA');

        return self::SUCCESS;
    }

    /** @return array{0: string, 1: int} */
    private function tierFor(int $fleetSize): array
    {
        if ($fleetSize <= self::TIER_SMALL_MAX_VEHICLES) {
            return ['1 (≤10 véhicules)', self::TIER_SMALL_FCFA];
        }

        if ($fleetSize <= self::TIER_MEDIUM_MAX_VEHICLES) {
            return ['2 (11-30 véhicules)', self::TIER_MEDIUM_FCFA];
        }

        return ['3 (31+ véhicules)', self::TIER_LARGE_FCFA];
    }
}
