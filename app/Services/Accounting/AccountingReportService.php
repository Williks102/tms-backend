<?php
// ══════════════════════════════════════════════════════════════════════════
// app/Services/Accounting/AccountingReportService.php
// ══════════════════════════════════════════════════════════════════════════
namespace App\Services\Accounting;

use App\Models\Accounting\AccountingAccount;
use App\Models\Accounting\AccountingEntryLine;
use Carbon\Carbon;

class AccountingReportService
{
    // ── Grand livre ────────────────────────────────────────────────────────

    public function ledger(AccountingAccount $account, ?string $from = null, ?string $to = null): array
    {
        $lines = AccountingEntryLine::with('entry')
            ->where('account_id', $account->id)
            ->whereHas('entry', function ($q) use ($from, $to) {
                if ($from) $q->whereDate('entry_date', '>=', $from);
                if ($to)   $q->whereDate('entry_date', '<=', $to);
            })
            ->get()
            ->sortBy(fn ($line) => $line->entry->entry_date)
            ->values();

        $balance = 0.0;
        $movements = $lines->map(function ($line) use (&$balance, $account) {
            $delta = $account->normal_side === 'debit'
                ? (float) $line->debit - (float) $line->credit
                : (float) $line->credit - (float) $line->debit;
            $balance += $delta;

            return [
                'entry_id'  => $line->entry_id,
                'reference' => $line->entry->reference,
                'date'      => $line->entry->entry_date->toDateString(),
                'label'     => $line->label ?? $line->entry->label,
                'debit'     => (float) $line->debit,
                'credit'    => (float) $line->credit,
                'balance'   => round($balance, 2),
            ];
        });

        return [
            'account'   => $account,
            'movements' => $movements,
            'balance'   => round($balance, 2),
        ];
    }

    // ── Balance générale ──────────────────────────────────────────────────

    public function trialBalance(): array
    {
        $accounts = AccountingAccount::active()->orderBy('code')->get();

        $totalDebit  = 0.0;
        $totalCredit = 0.0;

        $rows = $accounts->map(function ($account) use (&$totalDebit, &$totalCredit) {
            $debit  = (float) $account->lines()->sum('debit');
            $credit = (float) $account->lines()->sum('credit');
            $totalDebit  += $debit;
            $totalCredit += $credit;

            $solde = $account->normal_side === 'debit' ? $debit - $credit : $credit - $debit;

            return [
                'account' => $account,
                'debit'   => round($debit, 2),
                'credit'  => round($credit, 2),
                'solde'   => round($solde, 2),
            ];
        })->filter(fn ($row) => $row['debit'] > 0 || $row['credit'] > 0)->values();

        return [
            'rows'         => $rows,
            'total_debit'  => round($totalDebit, 2),
            'total_credit' => round($totalCredit, 2),
            'is_balanced'  => abs($totalDebit - $totalCredit) < 0.01,
        ];
    }

    // ── Compte de résultat ────────────────────────────────────────────────

    public function incomeStatement(?string $from = null, ?string $to = null): array
    {
        $from = $from ?? Carbon::now()->startOfYear()->toDateString();
        $to   = $to ?? Carbon::now()->toDateString();

        $charges  = $this->classTotal(6, $from, $to);
        $produits = $this->classTotal(7, $from, $to);

        return [
            'from'         => $from,
            'to'           => $to,
            'charges'      => $charges,
            'produits'     => $produits,
            'resultat_net' => round($produits['total'] - $charges['total'], 2),
        ];
    }

    // ── Bilan simplifié ───────────────────────────────────────────────────
    // Limite connue: pas d'immobilisations/amortissements modélisés — les
    // classes 2/1 (hors résultat) ne sont pas incluses dans ce bilan.

    public function balanceSheet(): array
    {
        $tresorerie = $this->classAccountsWithBalance(5);
        $tiers      = $this->classAccountsWithBalance(4);
        $income     = $this->incomeStatement();

        return [
            'tresorerie'   => $tresorerie,
            'tiers'        => $tiers,
            'resultat_net' => $income['resultat_net'],
            'periode'      => ['from' => $income['from'], 'to' => $income['to']],
        ];
    }

    private function classTotal(int $class, string $from, string $to): array
    {
        $accounts = AccountingAccount::active()->where('class', $class)->get();

        $lines = $accounts->map(function ($account) use ($from, $to) {
            $debit = (float) $account->lines()
                ->whereHas('entry', fn ($q) => $q->whereDate('entry_date', '>=', $from)->whereDate('entry_date', '<=', $to))
                ->sum('debit');
            $credit = (float) $account->lines()
                ->whereHas('entry', fn ($q) => $q->whereDate('entry_date', '>=', $from)->whereDate('entry_date', '<=', $to))
                ->sum('credit');
            $solde = $account->normal_side === 'debit' ? $debit - $credit : $credit - $debit;

            return ['account' => $account, 'montant' => round($solde, 2)];
        })->filter(fn ($row) => $row['montant'] != 0)->values();

        return ['lines' => $lines, 'total' => round((float) $lines->sum('montant'), 2)];
    }

    private function classAccountsWithBalance(int $class): array
    {
        return AccountingAccount::active()->where('class', $class)->get()
            ->map(function ($account) {
                $debit  = (float) $account->lines()->sum('debit');
                $credit = (float) $account->lines()->sum('credit');
                $solde  = $account->normal_side === 'debit' ? $debit - $credit : $credit - $debit;

                return ['account' => $account, 'solde' => round($solde, 2)];
            })
            ->filter(fn ($row) => $row['solde'] != 0)
            ->values()
            ->all();
    }
}
