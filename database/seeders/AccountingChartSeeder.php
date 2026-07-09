<?php
// ══════════════════════════════════════════════════════════════════════════
// database/seeders/AccountingChartSeeder.php
// ══════════════════════════════════════════════════════════════════════════
namespace Database\Seeders;

use App\Models\Accounting\AccountingAccount;
use App\Models\Accounting\AccountingJournal;
use Illuminate\Database\Seeder;

class AccountingChartSeeder extends Seeder
{
    public function run(): void
    {
        $journals = [
            ['code' => 'VE', 'label' => 'Journal des ventes'],
            ['code' => 'AC', 'label' => 'Journal des achats'],
            ['code' => 'CA', 'label' => 'Journal de caisse'],
            ['code' => 'BQ', 'label' => 'Journal de banque'],
            ['code' => 'OD', 'label' => 'Opérations diverses'],
        ];

        foreach ($journals as $journal) {
            AccountingJournal::updateOrCreate(['code' => $journal['code']], $journal);
        }

        // Sous-ensemble SYSCOHADA révisé pertinent pour une société de
        // transport de voyageurs — extensible ensuite via POST /comptabilite/accounts.
        $accounts = [
            ['code' => '101',  'label' => 'Capital social',                                'class' => 1, 'normal_side' => 'credit'],
            ['code' => '120',  'label' => 'Résultat net — bénéfice',                        'class' => 1, 'normal_side' => 'credit'],
            ['code' => '129',  'label' => 'Résultat net — perte',                           'class' => 1, 'normal_side' => 'debit'],
            ['code' => '245',  'label' => 'Matériel de transport',                          'class' => 2, 'normal_side' => 'debit'],
            ['code' => '2845', 'label' => 'Amortissements — matériel de transport',         'class' => 2, 'normal_side' => 'credit'],
            ['code' => '401',  'label' => 'Fournisseurs',                                   'class' => 4, 'normal_side' => 'credit'],
            ['code' => '411',  'label' => 'Clients',                                        'class' => 4, 'normal_side' => 'debit'],
            ['code' => '421',  'label' => 'Personnel, rémunérations dues',                  'class' => 4, 'normal_side' => 'credit'],
            ['code' => '431',  'label' => 'Sécurité sociale (CNPS)',                        'class' => 4, 'normal_side' => 'credit'],
            ['code' => '447',  'label' => 'État, impôts retenus à la source',               'class' => 4, 'normal_side' => 'credit'],
            ['code' => '471',  'label' => "Compte d'attente",                               'class' => 4, 'normal_side' => 'debit'],
            ['code' => '521',  'label' => 'Banques',                                        'class' => 5, 'normal_side' => 'debit'],
            ['code' => '571',  'label' => 'Caisse',                                         'class' => 5, 'normal_side' => 'debit'],
            ['code' => '6052', 'label' => 'Carburants et lubrifiants',                      'class' => 6, 'normal_side' => 'debit'],
            ['code' => '622',  'label' => 'Entretien, réparations et maintenance',          'class' => 6, 'normal_side' => 'debit'],
            ['code' => '631',  'label' => 'Impôts et taxes directs',                        'class' => 6, 'normal_side' => 'debit'],
            ['code' => '65',   'label' => 'Autres charges',                                 'class' => 6, 'normal_side' => 'debit'],
            ['code' => '661',  'label' => 'Rémunérations directes versées au personnel',    'class' => 6, 'normal_side' => 'debit'],
            ['code' => '664',  'label' => 'Charges sociales',                               'class' => 6, 'normal_side' => 'debit'],
            ['code' => '706',  'label' => 'Services vendus — transport de voyageurs',       'class' => 7, 'normal_side' => 'credit'],
            ['code' => '7071', 'label' => 'Prestations de service — fret/courrier',         'class' => 7, 'normal_side' => 'credit'],
            ['code' => '758',  'label' => 'Produits divers',                                'class' => 7, 'normal_side' => 'credit'],
        ];

        foreach ($accounts as $account) {
            AccountingAccount::updateOrCreate(['code' => $account['code']], $account);
        }

        $this->command->info('  Plan comptable OHADA: ' . count($accounts) . ' comptes, ' . count($journals) . ' journaux');
    }
}
