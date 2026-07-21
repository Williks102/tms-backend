<?php
// ══════════════════════════════════════════════════════════════════════════
// app/Console/Commands/BillingReportCommand.php
//
// Rapport de facturation SaaS — usage interne au porteur du projet
// uniquement. Logique déléguée à Services/SuperAdmin/BillingService (partagée
// avec SuperAdminController, voir CLAUDE.md § Revente SaaS). Chaque compagnie
// cliente a son propre déploiement isolé (runbook-nouveau-client.md), donc
// ce rapport se lance par déploiement, via la console Railway du client :
//
//   php artisan tms:billing-report [--month=AAAA-MM]
//   php artisan tms:billing-report --tiers   # grille tarifaire seule, sans DB
// ══════════════════════════════════════════════════════════════════════════
namespace App\Console\Commands;

use App\Services\SuperAdmin\BillingService;
use Illuminate\Console\Command;

class BillingReportCommand extends Command
{
    protected $signature = 'tms:billing-report
        {--month= : Mois au format AAAA-MM, défaut = mois en cours}
        {--tiers : Afficher uniquement la grille tarifaire, sans interroger la base}';

    protected $description = 'Rapport de facturation SaaS (usage vendeur uniquement) — taille de flotte et volume de billets en ligne du mois';

    public function __construct(private readonly BillingService $billing)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        if ($this->option('tiers')) {
            $this->showTiers();

            return self::SUCCESS;
        }

        $report = $this->billing->monthlyReport($this->option('month'));

        $this->info("Rapport de facturation — {$report['month_label']}");
        $this->newLine();
        $this->line("Taille de flotte actuelle  : {$report['fleet_size']} véhicule(s) → palier {$report['tier']['label']}");
        $this->line('Abonnement mensuel         : '.number_format($report['tier']['monthly_fcfa'], 0, ',', ' ').' FCFA');
        $this->newLine();
        $this->line("Billets en ligne vendus    : {$report['online_tickets_count']}");
        $this->line('Volume billets en ligne    : '.number_format($report['online_volume_fcfa'], 0, ',', ' ').' FCFA');
        $this->line('Commission ('.$report['commission_per_ticket_fcfa'].' FCFA/billet) : '.number_format($report['commission_fcfa'], 0, ',', ' ').' FCFA');
        $this->newLine();
        $this->info('Total à facturer ce mois   : '.number_format($report['total_fcfa'], 0, ',', ' ').' FCFA');

        return self::SUCCESS;
    }

    // Ne touche jamais la base — utilisable hors d'un déploiement client
    // précis (ex. depuis n'importe quel poste, en rendez-vous commercial).
    private function showTiers(): void
    {
        $this->info('Grille tarifaire TMS — illustrative, non validée par de vrais prospects');
        $this->newLine();

        foreach ($this->billing->tierGrid() as $tier) {
            $this->line(str_pad($tier['label'], 28).': '.number_format($tier['monthly_fcfa'], 0, ',', ' ').' FCFA/mois');
        }

        $this->newLine();
        $this->line('Commission billets en ligne : '.BillingService::COMMISSION_PER_ONLINE_TICKET_FCFA.' FCFA/billet');
        $this->newLine();
        $this->line('Frais de mise en place unique : ~'.BillingService::SETUP_FEE_RANGE_FCFA.' FCFA (offert pour le premier client de référence)');
    }
}
