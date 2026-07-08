<?php
// ══════════════════════════════════════════════════════════════════════════
// app/Services/Accounting/CashVoucherService.php
// ══════════════════════════════════════════════════════════════════════════
namespace App\Services\Accounting;

use App\Models\Accounting\CashVoucher;

class CashVoucherService
{
    public function __construct(
        private readonly AccountingEntryService $accounting,
    ) {}

    public function request(array $data, int $requestedBy): CashVoucher
    {
        return CashVoucher::create([
            'reference'        => $this->nextReference($data['type']),
            'type'             => $data['type'],
            'amount_fcfa'      => $data['amount_fcfa'],
            'account_id'       => $data['account_id'],
            'label'            => $data['label'],
            'beneficiary_name' => $data['beneficiary_name'] ?? null,
            'status'           => 'pending',
            'requested_by'     => $requestedBy,
        ]);
    }

    /**
     * @throws \RuntimeException si le bon n'est plus en attente
     */
    public function approve(CashVoucher $voucher, int $approvedBy): CashVoucher
    {
        if (!$voucher->isPending()) {
            throw new \RuntimeException("Ce bon n'est plus en attente (statut: {$voucher->status})");
        }

        $amount  = (float) $voucher->amount_fcfa;
        $account = $voucher->account; // AccountingAccount de contrepartie

        $entry = $this->accounting->post(
            journalCode: 'CA',
            label: "{$voucher->reference} — {$voucher->label}",
            lines: $voucher->isSortie()
                ? [
                    ['account' => $account, 'debit' => $amount],
                    ['account' => '571', 'credit' => $amount],
                ]
                : [
                    ['account' => '571', 'debit' => $amount],
                    ['account' => $account, 'credit' => $amount],
                ],
            source: $voucher,
            userId: $approvedBy,
        );

        $voucher->update([
            'status'               => 'approved',
            'approved_by'          => $approvedBy,
            'approved_at'          => now(),
            'accounting_entry_id'  => $entry->id,
        ]);

        return $voucher->fresh(['account', 'accountingEntry.lines']);
    }

    /**
     * @throws \RuntimeException si le bon n'est plus en attente
     */
    public function reject(CashVoucher $voucher, int $approvedBy, string $reason): CashVoucher
    {
        if (!$voucher->isPending()) {
            throw new \RuntimeException("Ce bon n'est plus en attente (statut: {$voucher->status})");
        }

        $voucher->update([
            'status'           => 'rejected',
            'approved_by'      => $approvedBy,
            'approved_at'      => now(),
            'rejection_reason' => $reason,
        ]);

        return $voucher->fresh();
    }

    private function nextReference(string $type): string
    {
        $prefix = $type === 'sortie' ? 'BSC' : 'BEC';
        $year   = (int) now()->year;
        $count  = CashVoucher::where('type', $type)->whereYear('created_at', $year)->count();

        return sprintf('%s-%d-%06d', $prefix, $year, $count + 1);
    }
}
