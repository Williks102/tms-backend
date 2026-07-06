<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Models\Driver;
use App\Models\DisciplinaryRecord;
use App\Models\User;
use App\Services\Hr\DisciplinaryRecordService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DisciplinaryRecordController extends Controller
{
    public function __construct(
        private readonly DisciplinaryRecordService $service,
    ) {}

    // GET /api/v1/hr/disciplinary
    public function index(Request $request): JsonResponse
    {
        $query = DisciplinaryRecord::with(['employable', 'issuedBy']);

        if ($request->filled('employable_type') && $request->filled('employable_id')) {
            $modelClass = $request->employable_type === 'driver' ? Driver::class : User::class;
            $query->where('employable_type', $modelClass)->where('employable_id', $request->employable_id);
        }

        $records = $query->latest('issued_at')->paginate($request->integer('per_page', 20));

        return response()->json($records);
    }

    // POST /api/v1/hr/disciplinary
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'employable_type' => 'required|in:user,driver',
            'employable_id'   => 'required|integer',
            'type'            => 'required|in:avertissement_verbal,avertissement_ecrit,mise_a_pied,autre',
            'description'     => 'required|string',
        ]);

        $modelClass = $data['employable_type'] === 'driver' ? Driver::class : User::class;
        $employable = $modelClass::findOrFail($data['employable_id']);

        $record = $this->service->create($employable, $data, $request->user()->id);

        return response()->json([
            'message' => 'Enregistrement disciplinaire créé',
            'record'  => $record,
        ], 201);
    }
}
