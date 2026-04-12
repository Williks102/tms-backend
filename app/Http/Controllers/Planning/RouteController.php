<?php

namespace App\Http\Controllers\Planning;

use App\Http\Controllers\Controller;
use App\Http\Resources\Planning\RouteResource;
use App\Models\Route;
use App\Models\RouteStop;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class RouteController extends Controller
{
    // ─────────────────────────────────────────────────────────────────────
    // GET /api/v1/planning/routes
    // ─────────────────────────────────────────────────────────────────────
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Route::active();

        if ($request->filled('origin')) {
            $query->where('origin_city', 'like', "%{$request->origin}%");
        }

        if ($request->filled('destination')) {
            $query->where('destination_city', 'like', "%{$request->destination}%");
        }

        if ($request->boolean('with_stops')) {
            $query->with('stops');
        }

        $routes = $query->orderBy('code')->paginate($request->integer('per_page', 30));

        return RouteResource::collection($routes);
    }

    // ─────────────────────────────────────────────────────────────────────
    // POST /api/v1/planning/routes
    // ─────────────────────────────────────────────────────────────────────
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code'                   => 'required|string|max:20|unique:routes,code',
            'name'                   => 'required|string|max:100',
            'origin_city'            => 'required|string|max:100',
            'destination_city'       => 'required|string|max:100',
            'distance_km'            => 'required|numeric|min:1',
            'estimated_duration_min' => 'required|integer|min:1',
            'is_dynamic'             => 'boolean',
            'base_fare'              => 'required|numeric|min:0',
        ]);

        $route = Route::create($data);

        return response()->json([
            'message' => 'Ligne créée avec succès',
            'route'   => new RouteResource($route),
        ], 201);
    }

    // ─────────────────────────────────────────────────────────────────────
    // GET /api/v1/planning/routes/{route}
    // ─────────────────────────────────────────────────────────────────────
    public function show(Route $route): RouteResource
    {
        $route->load('stops');
        return new RouteResource($route);
    }

    // ─────────────────────────────────────────────────────────────────────
    // PUT /api/v1/planning/routes/{route}
    // ─────────────────────────────────────────────────────────────────────
    public function update(Request $request, Route $route): JsonResponse
    {
        $data = $request->validate([
            'code'                   => "string|max:20|unique:routes,code,{$route->id}",
            'name'                   => 'string|max:100',
            'origin_city'            => 'string|max:100',
            'destination_city'       => 'string|max:100',
            'distance_km'            => 'numeric|min:1',
            'estimated_duration_min' => 'integer|min:1',
            'is_dynamic'             => 'boolean',
            'base_fare'              => 'numeric|min:0',
            'is_active'              => 'boolean',
        ]);

        $route->update($data);

        return response()->json([
            'message' => 'Ligne mise à jour',
            'route'   => new RouteResource($route->fresh('stops')),
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────
    // DELETE /api/v1/planning/routes/{route}  (soft delete)
    // ─────────────────────────────────────────────────────────────────────
    public function destroy(Route $route): JsonResponse
    {
        // Vérifie qu'il n'y a pas de départs futurs sur cette ligne
        $futureDepartures = $route->departures()
            ->where('departure_datetime', '>', now())
            ->whereNotIn('status', ['cancelled'])
            ->count();

        if ($futureDepartures > 0) {
            return response()->json([
                'message' => "Impossible de supprimer: {$futureDepartures} départ(s) futur(s) actif(s) sur cette ligne.",
            ], 422);
        }

        $route->update(['is_active' => false]);
        $route->delete(); // soft delete

        return response()->json(['message' => 'Ligne désactivée avec succès']);
    }

    // ─────────────────────────────────────────────────────────────────────
    // POST /api/v1/planning/routes/{route}/stops
    // ─────────────────────────────────────────────────────────────────────
    public function addStop(Request $request, Route $route): JsonResponse
    {
        if (!$route->is_dynamic) {
            return response()->json([
                'message' => 'Les arrêts intermédiaires ne sont disponibles que pour les lignes dynamiques.',
            ], 422);
        }

        $data = $request->validate([
            'city_name'               => 'required|string|max:100',
            'stop_order'              => 'required|integer|min:1',
            'distance_from_origin_km' => 'required|numeric|min:0.1',
            'fare_from_origin'        => 'required|numeric|min:0',
        ]);

        // Vérifie les conflits d'ordre et de ville
        $conflictOrder = $route->stops()->where('stop_order', $data['stop_order'])->exists();
        if ($conflictOrder) {
            return response()->json([
                'message' => "L'ordre {$data['stop_order']} est déjà pris sur cette ligne.",
            ], 422);
        }

        $stop = $route->stops()->create($data);

        return response()->json([
            'message' => 'Arrêt ajouté',
            'stop'    => $stop,
        ], 201);
    }
}
