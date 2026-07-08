<?php
// ══════════════════════════════════════════════════════════════════════════
// app/Services/Accounting/PayrollService.php
// ══════════════════════════════════════════════════════════════════════════
namespace App\Services\Accounting;

use App\Models\Accounting\Payslip;
use App\Models\Accounting\PayslipLine;
use App\Models\Driver;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class PayrollService
{
    public function __construct(
        private readonly AccountingEntryService $accounting,
    ) {}

    /**
     * Crée un bulletin brouillon par employé actif (Users + Drivers avec
     * base_salary_fcfa renseigné) sans bulletin existant pour cette période.
     * Pas de tout-ou-rien — une entrée déjà existante est simplement ignorée.
     *
     * @return array{created: int, skipped: int}
     */
    public function generateDraftsForPeriod(string $period): array
    {
        $created = 0;
        $skipped = 0;

        $employables = collect()
            ->concat(User::whereNotNull('base_salary_fcfa')->where('base_salary_fcfa', '>', 0)->get())
            ->concat(Driver::whereNotNull('base_salary_fcfa')->where('base_salary_fcfa', '>', 0)->get());

        foreach ($employables as $employable) {
            $exists = Payslip::where('employable_type', $employable::class)
                ->where('employable_id', $employable->getKey())
                ->where('period', $period)
                ->exists();

            if ($exists) {
                $skipped++;
                continue;
            }

            $payslip = Payslip::create([
                'employable_type' => $employable::class,
                'employable_id'   => $employable->getKey(),
                'period'          => $period,
                'status'          => 'draft',
            ]);

            PayslipLine::create([
                'payslip_id'  => $payslip->id,
                'type'        => 'gain',
                'label'       => 'Salaire de base',
                'amount_fcfa' => $employable->base_salary_fcfa,
            ]);

            $this->recalculate($payslip);
            $created++;
        }

        return ['created' => $created, 'skipped' => $skipped];
    }

    public function recalculate(Payslip $payslip): Payslip
    {
        $lines      = $payslip->lines()->get();
        $gross      = (float) $lines->where('type', 'gain')->sum('amount_fcfa');
        $deductions = (float) $lines->where('type', 'retenue')->sum('amount_fcfa');

        $payslip->update([
            'gross_amount_fcfa'      => $gross,
            'deductions_amount_fcfa' => $deductions,
            'net_amount_fcfa'        => $gross - $deductions,
        ]);

        return $payslip->fresh();
    }

    /**
     * Remplace toutes les lignes d'un bulletin encore en brouillon — le
     * comptable ajuste librement gains/retenues avant validation.
     *
     * @param array<int, array{type: string, label: string, amount_fcfa: float, account_id?: int|null}> $lines
     *
     * @throws \RuntimeException si le bulletin n'est plus en brouillon
     */
    public function updateLines(Payslip $payslip, array $lines): Payslip
    {
        if (!$payslip->isDraft()) {
            throw new \RuntimeException("Ce bulletin n'est plus modifiable (statut: {$payslip->status})");
        }

        DB::transaction(function () use ($payslip, $lines) {
            $payslip->lines()->delete();

            foreach ($lines as $line) {
                PayslipLine::create([
                    'payslip_id'  => $payslip->id,
                    'type'        => $line['type'],
                    'label'       => $line['label'],
                    'amount_fcfa' => $line['amount_fcfa'],
                    'account_id'  => $line['account_id'] ?? null,
                ]);
            }
        });

        return $this->recalculate($payslip);
    }

    /**
     * Constate la charge de paie — écriture 1/2 : debit 661 (brut), credit 421
     * (net à payer) + credit du compte de chaque retenue (431/447... ou 421
     * par défaut si non renseigné).
     *
     * @throws \RuntimeException si le bulletin n'est plus en brouillon
     */
    public function validate(Payslip $payslip, int $userId): Payslip
    {
        if (!$payslip->isDraft()) {
            throw new \RuntimeException("Ce bulletin n'est plus en brouillon (statut: {$payslip->status})");
        }

        $payslip = $this->recalculate($payslip);
        $lines   = $payslip->lines()->with('account')->get();

        $gross = (float) $payslip->gross_amount_fcfa;
        $net   = (float) $payslip->net_amount_fcfa;

        $deductionsByAccount = [];
        foreach ($lines->where('type', 'retenue') as $line) {
            $code = $line->account?->code ?? '421';
            $deductionsByAccount[$code] = ($deductionsByAccount[$code] ?? 0) + (float) $line->amount_fcfa;
        }

        $entryLines = [
            ['account' => '661', 'debit' => $gross],
            ['account' => '421', 'credit' => $net],
        ];

        foreach ($deductionsByAccount as $code => $amount) {
            $entryLines[] = ['account' => $code, 'credit' => $amount];
        }

        $entry = $this->accounting->post(
            journalCode: 'OD',
            label: "Paie {$payslip->period} — bulletin #{$payslip->id}",
            lines: $entryLines,
            source: $payslip,
            userId: $userId,
        );

        $payslip->update([
            'status'              => 'validated',
            'validated_at'        => now(),
            'validation_entry_id' => $entry->id,
        ]);

        return $payslip->fresh(['lines.account', 'validationEntry.lines']);
    }

    /**
     * Règle le net en caisse — écriture 2/2 : debit 421 / credit 571. Solde le
     * compte de tiers personnel ouvert par validate().
     *
     * @throws \RuntimeException si le bulletin n'est pas validé
     */
    public function pay(Payslip $payslip, int $userId): Payslip
    {
        if (!$payslip->isValidated()) {
            throw new \RuntimeException("Ce bulletin doit être validé avant paiement (statut: {$payslip->status})");
        }

        $net = (float) $payslip->net_amount_fcfa;

        $entry = $this->accounting->post(
            journalCode: 'CA',
            label: "Règlement paie {$payslip->period} — bulletin #{$payslip->id}",
            lines: [
                ['account' => '421', 'debit' => $net],
                ['account' => '571', 'credit' => $net],
            ],
            source: $payslip,
            userId: $userId,
        );

        $payslip->update([
            'status'           => 'paid',
            'paid_at'          => now(),
            'payment_entry_id' => $entry->id,
        ]);

        return $payslip->fresh(['paymentEntry.lines']);
    }
}
