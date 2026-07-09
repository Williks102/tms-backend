<?php
// ══════════════════════════════════════════════════════════════════════════
// app/Services/Accounting/PayrollTaxService.php
//
// ⚠️ Calcule CNPS (part salariale) et ITS à partir du barème éditable
// (payroll_tax_brackets, voir PayrollTaxBracketSeeder) — valeurs de départ
// ILLUSTRATIVES, à faire valider par un expert-comptable. Voir CLAUDE.md
// § Paie pour le détail de la décision et les limites connues.
// ══════════════════════════════════════════════════════════════════════════
namespace App\Services\Accounting;

use App\Models\Accounting\PayrollTaxBracket;

class PayrollTaxService
{
    /**
     * CNPS — part salariale uniquement (retraite). Calcul par tranches
     * (moteur générique — voir applyBrackets()), mais le barème CNPS n'a
     * en pratique qu'une seule tranche plafonnée : équivaut à
     * min(brut, plafond) × taux.
     */
    public function calculateCnps(float $grossFcfa): float
    {
        return $this->applyBrackets(max(0, $grossFcfa), 'cnps');
    }

    /**
     * ITS — barème progressif appliqué par part de quotient familial. Base
     * imposable = brut moins la CNPS part salariale (pratique standard :
     * la cotisation retraite n'est pas elle-même imposable).
     */
    public function calculateIts(float $grossFcfa, float $partsFiscales = 1.0): float
    {
        $partsFiscales = max($partsFiscales, 1.0);

        $cnps        = $this->calculateCnps($grossFcfa);
        $taxableBase = max(0, $grossFcfa - $cnps);
        $perPart     = $taxableBase / $partsFiscales;
        $taxPerPart  = $this->applyBrackets($perPart, 'its');

        return round($taxPerPart * $partsFiscales, 2);
    }

    /**
     * Moteur de calcul par tranches marginales — chaque tranche ne taxe que
     * la portion du montant qui lui appartient. Une tranche avec
     * max_fcfa=null est ouverte (taux appliqué indéfiniment au-delà) ; une
     * tranche avec max_fcfa défini plafonne (rien n'est taxé au-delà si
     * c'est la dernière tranche du type — cas du plafond CNPS).
     */
    private function applyBrackets(float $amount, string $type): float
    {
        $brackets = PayrollTaxBracket::where('type', $type)->orderBy('min_fcfa')->get();

        $tax = 0.0;
        foreach ($brackets as $bracket) {
            $min = (float) $bracket->min_fcfa;
            if ($amount <= $min) {
                break;
            }

            $max     = $bracket->max_fcfa !== null ? (float) $bracket->max_fcfa : $amount;
            $taxable = min($amount, $max) - $min;

            if ($taxable > 0) {
                $tax += $taxable * ((float) $bracket->rate_percent / 100);
            }
        }

        return round($tax, 2);
    }
}
