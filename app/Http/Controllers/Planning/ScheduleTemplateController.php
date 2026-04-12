<?php

namespace App\Http\Controllers\Planning;

use App\Http\Controllers\Controller;
use App\Http\Requests\Planning\StoreScheduleTemplateRequest;
use App\Models\ScheduleTemplate;
use App\Services\Planning\ScheduleGeneratorService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ScheduleTemplateController extends Controller
{
    public function __construct(
        private readonly ScheduleGeneratorService $generator,
    ) {}

    // ─────────────────────────────────────────────────────────────────────
    // GET /api/v1/planning/templates
    // ─────────────────────────────────────────────────────────────────────
    public function index(Request $request): JsonResponse
    {
        $templates = ScheduleTemplate::with('route')
            ->active()
            ->when($request->filled('route_id'), fn($q) => $q->where('route_id', $request->route_id))
            ->orderBy('departure_time')
            ->paginate($request->integer('per_page', 20));

        return response()->json($templates);
    }

    // ─────────────────────────────────────────────────────────────────────
    // POST /api/v1/planning/templates
    // ─────────────────────────────────────────────────────────────────────
    public function store(StoreScheduleTemplateRequest $request): JsonResponse
    {
        $template = ScheduleTemplate::create($request->validated());

        return response()->json([
            'message'  => 'Gabarit horaire créé',
            'template' => $template->load('route'),
        ], 201);
    }

    // ─────────────────────────────────────────────────────────────────────
    // POST /api/v1/planning/templates/{template}/generate
    // Lance la génération des départs sur une période donnée
    // ─────────────────────────────────────────────────────────────────────
    public function generate(Request $request, ScheduleTemplate $template): JsonResponse
    {
        $data = $request->validate([
            'from' => 'required|date|after_or_equal:today',
            'to'   => 'required|date|after:from|before_or_equal:' . now()->addMonths(3)->toDateString(),
        ]);

        $from = Carbon::parse($data['from'])->startOfDay();
        $to   = Carbon::parse($data['to'])->endOfDay();

        $result = $this->generator->generateFromTemplate($template, $from, $to);

        return response()->json([
            'message' => "Génération terminée",
            'period'  => [
                'from' => $from->toDateString(),
                'to'   => $to->toDateString(),
            ],
            'result'  => $result,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────
    // DELETE /api/v1/planning/templates/{template}
    // ─────────────────────────────────────────────────────────────────────
    public function destroy(ScheduleTemplate $template): JsonResponse
    {
        // Soft delete : les départs déjà générés restent intacts
        $template->update(['is_active' => false]);
        $template->delete();

        return response()->json(['message' => 'Gabarit désactivé']);
    }
}
