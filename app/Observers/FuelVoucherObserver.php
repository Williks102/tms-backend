<?php
// ══════════════════════════════════════════════════════════════════════════
// app/Observers/FuelVoucherObserver.php
// ══════════════════════════════════════════════════════════════════════════
namespace App\Observers;

use App\Models\FuelVoucher;
use App\Services\Accounting\AccountingEntryService;
use Illuminate\Support\Facades\Log;

class FuelVoucherObserver
{
    public function __construct(
        private readonly AccountingEntryService $accounting,
    ) {}

    public function updated(FuelVoucher $voucher): void
    {
        if (!$voucher->wasChanged('status') || $voucher->status !== 'approved') {
            return;
        }

        // Point de reconnaissance de charge = approbation, cohérent avec
        // DashboardController::financeSummary() qui somme déjà total_cost
        // sur status=approved. Un souci comptable ne doit jamais faire
        // échouer l'approbation elle-même.
        try {
            $this->accounting->post(
                journalCode: 'AC',
                label: sprintf(
                    'Bon carburant #%d — %s (%s)',
                    $voucher->id,
                    $voucher->vehicle?->plate_number ?? 'véhicule inconnu',
                    $voucher->station_name ?? 'station non renseignée'
                ),
                lines: [
                    ['account' => '6052', 'debit' => (float) $voucher->total_cost],
                    ['account' => '571', 'credit' => (float) $voucher->total_cost],
                ],
                source: $voucher,
                userId: $voucher->approved_by,
            );
        } catch (\Throwable $e) {
            Log::error('Échec écriture comptable — approbation bon carburant', [
                'voucher_id' => $voucher->id,
                'error'      => $e->getMessage(),
            ]);
        }
    }
}
