<?php
// ══════════════════════════════════════════════════════════════════════════
// app/Http/Controllers/Tickets/TicketController.php
// ══════════════════════════════════════════════════════════════════════════
namespace App\Http\Controllers\Tickets;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tickets\StoreOnlineTicketRequest;
use App\Http\Requests\Tickets\StoreTicketRequest;
use App\Http\Requests\Tickets\UpdateTicketStatusRequest;
use App\Models\Departure;
use App\Models\Ticket;
use App\Services\Tickets\TicketService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TicketController extends Controller
{
    public function __construct(
        private readonly TicketService $ticketService,
    ) {}

    // GET /api/v1/tickets
    public function index(Request $request): JsonResponse
    {
        $query = Ticket::with(['departure.route', 'soldBy']);

        if ($request->filled('departure_id')) $query->where('departure_id', $request->departure_id);
        if ($request->filled('status'))       $query->where('status', $request->status);
        if ($request->filled('channel'))      $query->where('channel', $request->channel);
        if ($request->filled('search')) {
            $term = $request->string('search');
            $query->where(fn ($q) => $q
                ->where('reference', 'like', "%{$term}%")
                ->orWhere('passenger_name', 'like', "%{$term}%"));
        }

        $tickets = $query->latest('purchased_at')->paginate($request->integer('per_page', 20));

        return response()->json($tickets);
    }

    // POST /api/v1/tickets — vente au guichet (caissier/manager)
    public function store(StoreTicketRequest $request): JsonResponse
    {
        try {
            $ticket = $this->ticketService->purchasePhysical($request->validated(), $request->user()->id);

            return response()->json([
                'message' => "Billet {$ticket->reference} vendu",
                'ticket'  => $ticket,
            ], 201);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    // POST /api/v1/tickets/online — achat en ligne (public, hors auth:sanctum)
    public function storeOnline(StoreOnlineTicketRequest $request): JsonResponse
    {
        try {
            $ticket = $this->ticketService->purchaseOnline($request->validated());

            return response()->json([
                'message' => "Billet {$ticket->reference} confirmé",
                'ticket'  => $ticket,
            ], 201);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    // GET /api/v1/tickets/{ticket}
    public function show(Ticket $ticket): JsonResponse
    {
        $ticket->load(['departure.route', 'departure.vehicle', 'departure.driver', 'soldBy']);

        return response()->json($ticket);
    }

    // PATCH /api/v1/tickets/{ticket}/status
    public function updateStatus(UpdateTicketStatusRequest $request, Ticket $ticket): JsonResponse
    {
        try {
            $ticket = $this->ticketService->updateStatus($ticket, $request->validated('status'), $request->validated());

            return response()->json(['message' => 'Statut mis à jour', 'ticket' => $ticket]);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    // GET /api/v1/tickets/departure/{departure}/manifest — liste d'embarquement
    public function manifest(Departure $departure): JsonResponse
    {
        $tickets = $departure->tickets()
            ->active()
            ->orderByRaw('seat_number IS NULL, seat_number')
            ->orderBy('purchased_at')
            ->get();

        return response()->json([
            'departure' => $departure->load('route', 'vehicle'),
            'data'      => $tickets,
            'summary'   => [
                'total'    => $tickets->count(),
                'boarded'  => $tickets->where('status', 'boarded')->count(),
                'physical' => $tickets->where('channel', 'physical')->count(),
                'online'   => $tickets->where('channel', 'online')->count(),
            ],
        ]);
    }

    // GET /api/v1/tickets/stats
    public function stats(): JsonResponse
    {
        return response()->json([
            'today_count'   => Ticket::whereDate('purchased_at', now())->count(),
            'today_revenue' => (float) Ticket::whereDate('purchased_at', now())
                ->active()->sum('price_fcfa'),
            'by_channel'    => Ticket::whereDate('purchased_at', now())
                ->selectRaw('channel, COUNT(*) as count')
                ->groupBy('channel')->pluck('count', 'channel'),
            'by_status'     => Ticket::selectRaw('status, COUNT(*) as count')
                ->groupBy('status')->pluck('count', 'status'),
        ]);
    }
}
