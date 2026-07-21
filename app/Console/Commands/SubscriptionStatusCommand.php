<?php
// ══════════════════════════════════════════════════════════════════════════
// app/Console/Commands/SubscriptionStatusCommand.php
//
// Gère le statut d'abonnement SaaS de CE déploiement client. Logique déléguée
// à Services/SuperAdmin/BillingService (partagée avec SuperAdminController,
// voir CLAUDE.md § Revente SaaS). Sans option, affiche le statut actuel.
//
//   php artisan tms:subscription                                 # consulter
//   php artisan tms:subscription --set=suspended --note="Impayé depuis juin"
//   php artisan tms:subscription --set=active --until=2026-08-20 --note="Payé par Mobile Money le 20/07"
// ══════════════════════════════════════════════════════════════════════════
namespace App\Console\Commands;

use App\Services\SuperAdmin\BillingService;
use Illuminate\Console\Command;

class SubscriptionStatusCommand extends Command
{
    protected $signature = 'tms:subscription
        {--set= : active ou suspended — omettre pour seulement consulter}
        {--until= : Date payé jusqu\'à, format AAAA-MM-JJ (informatif, pas vérifié automatiquement)}
        {--note= : Note libre, ex. moyen de paiement et date}';

    protected $description = 'Consulter ou changer le statut d\'abonnement SaaS de ce déploiement (coupe le back-office si suspendu)';

    public function __construct(private readonly BillingService $billing)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $set = $this->option('set');

        if ($set !== null) {
            if (!in_array($set, ['active', 'suspended'], true)) {
                $this->error("--set doit être 'active' ou 'suspended', reçu : '{$set}'");

                return self::FAILURE;
            }

            $subscription = $this->billing->updateSubscription($set, $this->option('until'), $this->option('note'));
            $this->info("Statut mis à jour : {$subscription->status}");
        } else {
            $subscription = $this->billing->currentSubscription();
        }

        $this->line('');
        $this->line('Statut actuel  : '.($subscription->isActive() ? '<fg=green>active</>' : '<fg=red>suspended</>'));
        $this->line('Payé jusqu\'au : '.($subscription->paid_until?->format('d/m/Y') ?? '—'));
        $this->line('Note           : '.($subscription->note ?? '—'));

        if (!$subscription->isActive()) {
            $this->warn('Le back-office (routes authentifiées) est actuellement bloqué pour tout le personnel de ce client. Les pages publiques (billets, suivi colis, écran de gare) restent actives.');
        }

        return self::SUCCESS;
    }
}
