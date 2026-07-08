<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Accounting\AccountingAccount;
use App\Models\Accounting\AccountingEntry;
use App\Services\Accounting\AccountingEntryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class JournalEntryController extends Controller
{
    public function __construct(
        private readonly AccountingEntryService $accounting,
    ) {}

    // GET /api/v1/comptabilite/journals
    public function index(Request $request): JsonResponse
    {
        $entries = AccountingEntry::with(['journal', 'lines.account', 'createdBy'])
            ->when($request->journal, fn ($q) => $q->whereHas('journal', fn ($jq) => $jq->where('code', $request->journal)))
            ->when($request->from, fn ($q) => $q->whereDate('entry_date', '>=', $request->from))
            ->when($request->to, fn ($q) => $q->whereDate('entry_date', '<=', $request->to))
            ->when($request->source_type, fn ($q) => $q->where('source_type', $request->source_type))
            ->latest('entry_date')
            ->latest('id')
            ->paginate($request->integer('per_page', 30));

        return response()->json($entries);
    }

    // GET /api/v1/comptabilite/journals/{entry}
    public function show(AccountingEntry $entry): JsonResponse
    {
        return response()->json([
            'entry' => $entry->load(['journal', 'lines.account', 'createdBy', 'source']),
        ]);
    }

    // POST /api/v1/comptabilite/journals/manual — saisie d'une opération diverse (OD)
    public function storeManual(Request $request): JsonResponse
    {
        $data = $request->validate([
            'label'                => 'required|string|max:255',
            'lines'                => 'required|array|min:2',
            'lines.*.account_id'   => 'required|exists:accounting_accounts,id',
            'lines.*.debit'        => 'nullable|numeric|min:0',
            'lines.*.credit'       => 'nullable|numeric|min:0',
            'lines.*.label'        => 'nullable|string|max:150',
        ]);

        $accounts = AccountingAccount::whereIn('id', collect($data['lines'])->pluck('account_id'))->get()->keyBy('id');

        $lines = array_map(fn (array $line) => [
            'account' => $accounts[$line['account_id']],
            'debit'   => $line['debit'] ?? 0,
            'credit'  => $line['credit'] ?? 0,
            'label'   => $line['label'] ?? null,
        ], $data['lines']);

        try {
            $entry = $this->accounting->post(
                journalCode: 'OD',
                label: $data['label'],
                lines: $lines,
                userId: $request->user()->id,
            );

            return response()->json(['message' => 'Écriture enregistrée', 'entry' => $entry], 201);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }
}
