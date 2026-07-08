<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Accounting\AccountingAccount;
use App\Services\Accounting\AccountingReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function __construct(
        private readonly AccountingReportService $reports,
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
}
