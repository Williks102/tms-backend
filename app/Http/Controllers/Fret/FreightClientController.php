<?php

namespace App\Http\Controllers\Fret;

use App\Http\Controllers\Controller;
use App\Models\FreightClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FreightClientController extends Controller
{
    // GET /api/v1/fret/clients
    public function index(Request $request): JsonResponse
    {
        $query = FreightClient::query();

        if ($request->filled('search')) {
            $term = $request->string('search');
            $query->where(fn ($q) => $q
                ->where('company_name', 'like', "%{$term}%")
                ->orWhere('contact_name', 'like', "%{$term}%")
                ->orWhere('phone', 'like', "%{$term}%"));
        }

        return response()->json($query->latest()->paginate($request->integer('per_page', 20)));
    }

    // POST /api/v1/fret/clients
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'company_name' => 'required|string|max:150',
            'contact_name' => 'nullable|string|max:150',
            'phone'        => 'required|string|max:30',
            'email'        => 'nullable|email|max:150',
            'address'      => 'nullable|string|max:500',
            'notes'        => 'nullable|string|max:500',
        ]);

        $client = FreightClient::create($data);

        return response()->json(['message' => 'Client fret créé', 'client' => $client], 201);
    }

    // GET /api/v1/fret/clients/{client}
    public function show(FreightClient $client): JsonResponse
    {
        return response()->json([
            'client' => $client->load(['shipments' => fn ($q) => $q->latest()->limit(50)]),
        ]);
    }
}
