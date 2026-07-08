<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Accounting\AccountingAccount;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AccountController extends Controller
{
    // GET /api/v1/comptabilite/accounts
    public function index(Request $request): JsonResponse
    {
        $accounts = AccountingAccount::query()
            ->when(!$request->boolean('include_inactive'), fn ($q) => $q->active())
            ->when($request->class, fn ($q) => $q->where('class', $request->class))
            ->orderBy('code')
            ->get();

        return response()->json(['data' => $accounts]);
    }

    // POST /api/v1/comptabilite/accounts
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code'        => 'required|string|max:20|unique:accounting_accounts,code',
            'label'       => 'required|string|max:150',
            'class'       => 'required|integer|min:1|max:9',
            'normal_side' => 'required|in:debit,credit',
        ]);

        $account = AccountingAccount::create([...$data, 'is_active' => true]);

        return response()->json([
            'message' => 'Compte créé avec succès',
            'account' => $account,
        ], 201);
    }
}
