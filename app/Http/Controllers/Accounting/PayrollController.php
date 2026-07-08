<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Accounting\Payslip;
use App\Services\Accounting\PayrollService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PayrollController extends Controller
{
    public function __construct(
        private readonly PayrollService $service,
    ) {}

    // GET /api/v1/comptabilite/payslips
    public function index(Request $request): JsonResponse
    {
        $payslips = Payslip::with(['employable', 'lines.account'])
            ->when($request->period, fn ($q) => $q->where('period', $request->period))
            ->when($request->status, fn ($q) => $q->where('status', $request->status))
            ->latest('period')
            ->paginate($request->integer('per_page', 30));

        return response()->json($payslips);
    }

    // POST /api/v1/comptabilite/payslips/generate
    public function generate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'period' => 'required|regex:/^\d{4}-\d{2}$/',
        ]);

        $result = $this->service->generateDraftsForPeriod($data['period']);

        return response()->json([
            'message' => "{$result['created']} bulletin(s) créé(s), {$result['skipped']} déjà existant(s)",
            ...$result,
        ]);
    }

    // PUT /api/v1/comptabilite/payslips/{payslip}/lines
    public function updateLines(Request $request, Payslip $payslip): JsonResponse
    {
        $data = $request->validate([
            'lines'                    => 'required|array|min:1',
            'lines.*.type'             => 'required|in:gain,retenue',
            'lines.*.label'            => 'required|string|max:150',
            'lines.*.amount_fcfa'      => 'required|numeric|min:0',
            'lines.*.account_id'       => 'nullable|exists:accounting_accounts,id',
        ]);

        try {
            $payslip = $this->service->updateLines($payslip, $data['lines']);

            return response()->json(['message' => 'Lignes mises à jour', 'payslip' => $payslip->load('lines.account')]);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    // PATCH /api/v1/comptabilite/payslips/{payslip}/validate
    public function validatePayslip(Request $request, Payslip $payslip): JsonResponse
    {
        try {
            $payslip = $this->service->validate($payslip, $request->user()->id);

            return response()->json(['message' => 'Bulletin validé', 'payslip' => $payslip]);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    // PATCH /api/v1/comptabilite/payslips/{payslip}/pay
    public function pay(Request $request, Payslip $payslip): JsonResponse
    {
        try {
            $payslip = $this->service->pay($payslip, $request->user()->id);

            return response()->json(['message' => 'Bulletin payé', 'payslip' => $payslip]);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }
}
