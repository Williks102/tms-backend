<?php
// ══════════════════════════════════════════════════════════════════════════
// app/Http/Controllers/SuperAdmin/SuperAdminController.php
//
// Équivalent web des commandes tms:subscription / tms:billing-report — voir
// CLAUDE.md § Revente SaaS. Toutes les routes sont sous role:super_admin
// (voir routes/api.php), un rôle jamais assignable via la création de
// personnel classique et jamais seedé automatiquement pour un client (voir
// tms:create-super-admin). Le personnel d'une compagnie cliente ne doit
// jamais pouvoir atteindre ce contrôleur, quel que soit son rôle.
// ══════════════════════════════════════════════════════════════════════════
namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Services\SuperAdmin\BillingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SuperAdminController extends Controller
{
    public function __construct(private readonly BillingService $billing)
    {
    }

    // GET /api/v1/super-admin/tiers — grille tarifaire, ne touche pas la DB
    public function tiers(): JsonResponse
    {
        return response()->json([
            'data' => [
                'tiers'                      => $this->billing->tierGrid(),
                'commission_per_ticket_fcfa' => BillingService::COMMISSION_PER_ONLINE_TICKET_FCFA,
                'setup_fee_range_fcfa'       => BillingService::SETUP_FEE_RANGE_FCFA,
            ],
        ]);
    }

    // GET /api/v1/super-admin/billing-report?month=AAAA-MM
    public function billingReport(Request $request): JsonResponse
    {
        $request->validate(['month' => 'nullable|date_format:Y-m']);

        return response()->json(['data' => $this->billing->monthlyReport($request->query('month'))]);
    }

    // GET /api/v1/super-admin/subscription
    public function subscription(): JsonResponse
    {
        return response()->json(['data' => $this->billing->currentSubscription()]);
    }

    // PATCH /api/v1/super-admin/subscription
    public function updateSubscription(Request $request): JsonResponse
    {
        $data = $request->validate([
            'status'     => 'required|in:active,suspended',
            'paid_until' => 'nullable|date',
            'note'       => 'nullable|string|max:1000',
        ]);

        $subscription = $this->billing->updateSubscription($data['status'], $data['paid_until'] ?? null, $data['note'] ?? null);

        return response()->json([
            'message' => 'Statut d\'abonnement mis à jour',
            'data'    => $subscription,
        ]);
    }
}
