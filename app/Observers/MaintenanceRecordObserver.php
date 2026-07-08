<?php
// ══════════════════════════════════════════════════════════════════════════
// app/Observers/MaintenanceRecordObserver.php
// ══════════════════════════════════════════════════════════════════════════
namespace App\Observers;

use App\Models\MaintenanceRecord;
use App\Services\Accounting\AccountingEntryService;
use Illuminate\Support\Facades\Log;

class MaintenanceRecordObserver
{
    public function __construct(
        private readonly AccountingEntryService $accounting,
    ) {}

    public function created(MaintenanceRecord $record): void
    {
        if ((float) $record->cost_fcfa <= 0) {
            return;
        }

        try {
            $this->accounting->post(
                journalCode: 'AC',
                label: sprintf(
                    'Maintenance #%d — %s (%s)',
                    $record->id,
                    $record->vehicle?->plate_number ?? 'véhicule inconnu',
                    $record->garage_name ?? 'garage non renseigné'
                ),
                lines: [
                    ['account' => '622', 'debit' => (float) $record->cost_fcfa],
                    ['account' => '571', 'credit' => (float) $record->cost_fcfa],
                ],
                source: $record,
                userId: $record->performed_by,
            );
        } catch (\Throwable $e) {
            Log::error('Échec écriture comptable — intervention maintenance', [
                'record_id' => $record->id,
                'error'     => $e->getMessage(),
            ]);
        }
    }
}
