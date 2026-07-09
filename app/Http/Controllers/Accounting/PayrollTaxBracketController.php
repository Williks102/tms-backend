<?php
// ══════════════════════════════════════════════════════════════════════════
// app/Http/Controllers/Accounting/PayrollTaxBracketController.php
// CRUD du barème CNPS/ITS — voir PayrollTaxBracketSeeder pour l'avertissement
// complet sur la fiabilité des valeurs de départ. Ce contrôleur est le point
// à utiliser pour les corriger, pas une réédition du seeder.
// ══════════════════════════════════════════════════════════════════════════
namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Accounting\PayrollTaxBracket;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PayrollTaxBracketController extends Controller
{
    // GET /api/v1/comptabilite/payroll-tax-brackets
    public function index(Request $request): JsonResponse
    {
        $brackets = PayrollTaxBracket::query()
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->string('type')))
            ->orderBy('type')
            ->orderBy('min_fcfa')
            ->get();

        return response()->json(['data' => $brackets]);
    }

    // POST /api/v1/comptabilite/payroll-tax-brackets
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'type'         => ['required', Rule::in(['cnps', 'its'])],
            'min_fcfa'     => 'required|numeric|min:0',
            'max_fcfa'     => 'nullable|numeric|gt:min_fcfa',
            'rate_percent' => 'required|numeric|min:0|max:100',
            'label'        => 'required|string|max:150',
            'sort_order'   => 'nullable|integer|min:0',
        ]);

        $bracket = PayrollTaxBracket::create($data);

        return response()->json(['message' => 'Tranche créée', 'bracket' => $bracket], 201);
    }

    // PUT /api/v1/comptabilite/payroll-tax-brackets/{bracket}
    public function update(Request $request, PayrollTaxBracket $bracket): JsonResponse
    {
        $data = $request->validate([
            'min_fcfa'     => 'sometimes|numeric|min:0',
            'max_fcfa'     => 'nullable|numeric|gt:min_fcfa',
            'rate_percent' => 'sometimes|numeric|min:0|max:100',
            'label'        => 'sometimes|string|max:150',
            'sort_order'   => 'nullable|integer|min:0',
        ]);

        $bracket->update($data);

        return response()->json(['message' => 'Tranche mise à jour', 'bracket' => $bracket->fresh()]);
    }

    // DELETE /api/v1/comptabilite/payroll-tax-brackets/{bracket}
    public function destroy(PayrollTaxBracket $bracket): JsonResponse
    {
        $bracket->delete();

        return response()->json(['message' => 'Tranche supprimée']);
    }
}
