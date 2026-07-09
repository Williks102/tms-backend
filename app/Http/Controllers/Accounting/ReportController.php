<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Accounting\AccountingAccount;
use App\Services\Accounting\AccountingReportService;
use App\Services\Export\CsvExportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function __construct(
        private readonly AccountingReportService $reports,
        private readonly CsvExportService $csv,
    ) {}

    // GET /api/v1/comptabilite/grand-livre/{account}
    public function ledger(Request $request, AccountingAccount $account): JsonResponse
    {
        return response()->json(
            $this->reports->ledger($account, $request->from, $request->to)
        );
    }

    // GET /api/v1/comptabilite/balance
    public function balance(): JsonResponse
    {
        return response()->json($this->reports->trialBalance());
    }

    // GET /api/v1/comptabilite/compte-resultat
    public function incomeStatement(Request $request): JsonResponse
    {
        return response()->json($this->reports->incomeStatement($request->from, $request->to));
    }

    // GET /api/v1/comptabilite/bilan
    public function balanceSheet(): JsonResponse
    {
        return response()->json($this->reports->balanceSheet());
    }

    // GET /api/v1/comptabilite/balance/export
    public function exportBalance(): StreamedResponse
    {
        $balance = $this->reports->trialBalance();

        $rows = collect($balance['rows'])->map(fn (array $row) => [
            $row['account']->code,
            $row['account']->label,
            $row['debit'],
            $row['credit'],
            $row['solde'],
        ]);

        return $this->csv->stream(
            ['Compte', 'Libellé', 'Débit', 'Crédit', 'Solde'],
            $rows,
            'balance-' . now()->format('Y-m-d') . '.csv',
        );
    }
}
