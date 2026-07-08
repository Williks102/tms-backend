<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Accounting\CashVoucher;
use App\Services\Accounting\CashVoucherService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CashVoucherController extends Controller
{
    public function __construct(
        private readonly CashVoucherService $service,
    ) {}

    // GET /api/v1/comptabilite/cash-vouchers
    public function index(Request $request): JsonResponse
    {
        $vouchers = CashVoucher::with(['account', 'requestedBy', 'approvedBy'])
            ->when($request->type, fn ($q) => $q->where('type', $request->type))
            ->when($request->status, fn ($q) => $q->where('status', $request->status))
            ->latest()
            ->paginate($request->integer('per_page', 20));

        return response()->json($vouchers);
    }

    // POST /api/v1/comptabilite/cash-vouchers
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'type'             => 'required|in:sortie,entree',
            'amount_fcfa'      => 'required|numeric|min:1',
            'account_id'       => 'required|exists:accounting_accounts,id',
            'label'            => 'required|string|max:255',
            'beneficiary_name' => 'nullable|string|max:150',
        ]);

        $voucher = $this->service->request($data, $request->user()->id);

        return response()->json([
            'message' => 'Bon de caisse créé — en attente d\'approbation',
            'voucher' => $voucher->load('account'),
        ], 201);
    }

    // PATCH /api/v1/comptabilite/cash-vouchers/{voucher}/approve
    public function approve(Request $request, CashVoucher $voucher): JsonResponse
    {
        try {
            $voucher = $this->service->approve($voucher, $request->user()->id);

            return response()->json(['message' => 'Bon approuvé', 'voucher' => $voucher]);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    // PATCH /api/v1/comptabilite/cash-vouchers/{voucher}/reject
    public function reject(Request $request, CashVoucher $voucher): JsonResponse
    {
        $data = $request->validate(['rejection_reason' => 'required|string|min:5']);

        try {
            $voucher = $this->service->reject($voucher, $request->user()->id, $data['rejection_reason']);

            return response()->json(['message' => 'Bon refusé', 'voucher' => $voucher]);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }
}
