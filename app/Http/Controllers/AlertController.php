<?php
// ══════════════════════════════════════════════════════════════════════════
// app/Http/Controllers/AlertController.php
// ══════════════════════════════════════════════════════════════════════════
namespace App\Http\Controllers;

use App\Models\SystemAlert;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AlertController extends Controller
{
    // GET /api/v1/alerts
    public function index(Request $request): JsonResponse
    {
        $query = SystemAlert::query();

        // Par défaut: alertes non résolues
        if ($request->boolean('resolved', false)) {
            $query->where('is_resolved', true);
        } else {
            $query->where('is_resolved', false);
        }

        if ($request->filled('severity')) {
            $query->where('severity', $request->severity);
        }

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        $alerts = $query->latest()->paginate(30);

        return response()->json($alerts);
    }

    // PATCH /api/v1/alerts/{alert}/resolve
    public function resolve(Request $request, SystemAlert $alert): JsonResponse
    {
        if ($alert->is_resolved) {
            return response()->json(['message' => 'Alerte déjà résolue'], 422);
        }

        $alert->resolve($request->user()->id);

        return response()->json([
            'message' => 'Alerte résolue',
            'alert'   => $alert->fresh(),
        ]);
    }

    // GET /api/v1/alerts/summary
    // Utilisé par le dashboard /live
    public function summary(): JsonResponse
    {
        $active = SystemAlert::where('is_resolved', false);

        $counts = (clone $active)
            ->selectRaw('severity, COUNT(*) as count')
            ->groupBy('severity')
            ->pluck('count', 'severity');

        $latest = (clone $active)
            ->latest()
            ->limit(5)
            ->get(['id', 'type', 'severity', 'message', 'entity_type', 'entity_id', 'created_at']);

        return response()->json([
            'critical' => $counts->get('critical', 0),
            'warning'  => $counts->get('warning', 0),
            'info'     => $counts->get('info', 0),
            'total'    => $counts->sum(),
            'latest'   => $latest,
        ]);
    }
}