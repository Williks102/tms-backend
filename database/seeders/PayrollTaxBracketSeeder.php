<?php
// ══════════════════════════════════════════════════════════════════════════
// database/seeders/PayrollTaxBracketSeeder.php
//
// ⚠️⚠️⚠️ TAUX ILLUSTRATIFS — À FAIRE VALIDER PAR UN EXPERT-COMPTABLE AVANT
// TOUT USAGE RÉEL. Ni le taux CNPS+plafond, ni les tranches ITS ci-dessous
// ne sont garantis à jour : la Côte d'Ivoire a réformé l'ITS en 2024
// (fusion ITS/CN/IGR) et les tranches exactes post-réforme n'ont pas pu
// être confirmées auprès d'une source officielle au moment de l'écriture
// (contrairement à PaiementPro, aucun document officiel fourni pour cette
// partie — voir CLAUDE.md § Paie, décision explicite du 2026-07-09).
// Le barème reste éditable sans déploiement via
// GET/POST/PUT/DELETE /comptabilite/payroll-tax-brackets — c'est le point
// à utiliser pour corriger ces valeurs, pas une réédition de ce seeder.
// ══════════════════════════════════════════════════════════════════════════
namespace Database\Seeders;

use App\Models\Accounting\PayrollTaxBracket;
use Illuminate\Database\Seeder;

class PayrollTaxBracketSeeder extends Seeder
{
    public function run(): void
    {
        $brackets = [
            // CNPS — retraite, part salariale seule (la part patronale et
            // les autres branches CNPS — prestations familiales, accidents
            // du travail — sont à la charge de l'employeur, pas retenues
            // sur le bulletin, donc non modélisées ici).
            ['type' => 'cnps', 'min_fcfa' => 0, 'max_fcfa' => 1647315, 'rate_percent' => 6.3, 'label' => 'CNPS — Retraite (part salariale)', 'sort_order' => 1],

            // ITS — barème progressif mensuel, appliqué par part de quotient
            // familial (parts_fiscales). Dernière tranche max_fcfa=null :
            // taux marginal appliqué sans plafond au-delà.
            ['type' => 'its', 'min_fcfa' => 0,        'max_fcfa' => 75000,    'rate_percent' => 0,  'label' => 'ITS — Tranche exonérée',    'sort_order' => 1],
            ['type' => 'its', 'min_fcfa' => 75000,    'max_fcfa' => 240000,   'rate_percent' => 10, 'label' => 'ITS — Tranche 2',            'sort_order' => 2],
            ['type' => 'its', 'min_fcfa' => 240000,   'max_fcfa' => 800000,   'rate_percent' => 15, 'label' => 'ITS — Tranche 3',            'sort_order' => 3],
            ['type' => 'its', 'min_fcfa' => 800000,   'max_fcfa' => 2400000,  'rate_percent' => 20, 'label' => 'ITS — Tranche 4',            'sort_order' => 4],
            ['type' => 'its', 'min_fcfa' => 2400000,  'max_fcfa' => 8000000,  'rate_percent' => 25, 'label' => 'ITS — Tranche 5',            'sort_order' => 5],
            ['type' => 'its', 'min_fcfa' => 8000000,  'max_fcfa' => null,     'rate_percent' => 35, 'label' => 'ITS — Tranche marginale',    'sort_order' => 6],
        ];

        foreach ($brackets as $bracket) {
            PayrollTaxBracket::updateOrCreate(
                ['type' => $bracket['type'], 'min_fcfa' => $bracket['min_fcfa']],
                $bracket,
            );
        }

        $this->command->warn('  ⚠️  Barème CNPS/ITS: valeurs ILLUSTRATIVES non confirmées — à faire valider par un expert-comptable (voir commentaire du seeder).');
    }
}
