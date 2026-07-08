<?php
// ══════════════════════════════════════════════════════════════════════════
// app/Services/Accounting/AccountingEntryService.php
// ══════════════════════════════════════════════════════════════════════════
namespace App\Services\Accounting;

use App\Models\Accounting\AccountingAccount;
use App\Models\Accounting\AccountingEntry;
use App\Models\Accounting\AccountingEntryLine;
use App\Models\Accounting\AccountingJournal;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class AccountingEntryService
{
    // Tolérance d'arrondi (centimes) — une comparaison flottante stricte est fragile.
    private const BALANCE_TOLERANCE = 0.01;

    /**
     * Poste une écriture en partie double équilibrée. Point d'entrée unique
     * et réutilisable pour tout le module — l'équivalent comptable
     * d'AlertDispatcher::create() pour les alertes.
     *
     * @param array<int, array{account: string|AccountingAccount, debit?: float, credit?: float, label?: string}> $lines
     *
     * @throws \RuntimeException si la somme des débits != somme des crédits, ou si moins de 2 lignes
     */
    public function post(
        string $journalCode,
        string $label,
        array $lines,
        ?Model $source = null,
        ?int $userId = null,
    ): AccountingEntry {
        if (count($lines) < 2) {
            throw new \RuntimeException('Une écriture nécessite au moins 2 lignes');
        }

        $totalDebit  = 0.0;
        $totalCredit = 0.0;
        foreach ($lines as $line) {
            $totalDebit  += $line['debit'] ?? 0;
            $totalCredit += $line['credit'] ?? 0;
        }

        if (abs($totalDebit - $totalCredit) > self::BALANCE_TOLERANCE) {
            throw new \RuntimeException(sprintf(
                'Écriture déséquilibrée: débit %.2f ≠ crédit %.2f (%s)',
                $totalDebit,
                $totalCredit,
                $label
            ));
        }

        return DB::transaction(function () use ($journalCode, $label, $lines, $source, $userId) {
            $journal = AccountingJournal::where('code', $journalCode)->firstOrFail();

            $entry = AccountingEntry::create([
                'journal_id'  => $journal->id,
                'reference'   => $this->nextReference($journal),
                'entry_date'  => now()->toDateString(),
                'label'       => $label,
                'source_type' => $source?->getMorphClass(),
                'source_id'   => $source?->getKey(),
                'created_by'  => $userId,
            ]);

            foreach ($lines as $line) {
                $account = $line['account'] instanceof AccountingAccount
                    ? $line['account']
                    : AccountingAccount::findByCode($line['account']);

                AccountingEntryLine::create([
                    'entry_id'   => $entry->id,
                    'account_id' => $account->id,
                    'debit'      => $line['debit'] ?? 0,
                    'credit'     => $line['credit'] ?? 0,
                    'label'      => $line['label'] ?? $label,
                ]);
            }

            return $entry->fresh(['lines.account', 'journal']);
        });
    }

    private function nextReference(AccountingJournal $journal): string
    {
        $year  = (int) now()->year;
        $count = AccountingEntry::where('journal_id', $journal->id)
            ->whereYear('entry_date', $year)
            ->count();

        return sprintf('%s-%d-%06d', $journal->code, $year, $count + 1);
    }
}
