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
use App\Models\Route;
use App\Models\Ticket;
use App\Services\Tickets\TicketService;
use Carbon\Carbon;
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
        $query = Ticket::with(['departure.route', 'departure.gate', 'destinationStop', 'soldBy']);

        if ($request->filled('departure_id')) $query->where('departure_id', $request->departure_id);
        if ($request->filled('status'))       $query->where('status', $request->status);
        if ($request->filled('channel'))      $query->where('channel', $request->channel);
        if ($request->filled('sold_by'))      $query->where('sold_by', $request->integer('sold_by'));
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
    // Crée un billet "pending" et retourne l'URL de paiement PaiementPro à
    // laquelle rediriger le client — ne retourne JAMAIS un billet "paid" à
    // cet appel, la confirmation vient uniquement du webhook (voir
    // PaiementProWebhookController).
    public function storeOnline(StoreOnlineTicketRequest $request): JsonResponse
    {
        try {
            $result = $this->ticketService->purchaseOnline($request->validated());

            return response()->json([
                'message'     => "Billet {$result['ticket']->reference} en attente de paiement",
                'ticket'      => $result['ticket'],
                'payment_url' => $result['payment_url'],
            ], 201);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    // GET /api/v1/tickets/online/status?ref= — public, pour la page
    // /billets/retour qui interroge ce point après redirection PaiementPro
    // (la redirection navigateur n'est pas fiable seule : la confirmation
    // serveur-à-serveur du webhook peut arriver avant ou après).
    public function onlineStatus(Request $request): JsonResponse
    {
        $request->validate(['ref' => 'required|string']);

        // PaiementPro ajoute ses propres paramètres à returnURL avec un "?"
        // même quand notre URL en a déjà un (returnURL?ref=TOKEN devient
        // returnURL?ref=TOKEN?merchantId=..., observé en sandbox réel) — le
        // "?" superflu et tout ce qui suit ne font jamais partie du token.
        $ref = strtok((string) $request->string('ref'), '?');

        $ticket = Ticket::where('payment_token', $ref)->first();

        if (!$ticket) {
            return response()->json(['status' => 'unknown'], 404);
        }

        return response()->json([
            'status' => $ticket->status,
            'ticket' => in_array($ticket->status, ['paid', 'boarded'], true)
                ? $ticket->load(['departure.route', 'departure.gate', 'destinationStop'])
                : null,
        ]);
    }

    // GET /api/v1/tickets/online/routes — public (hors auth:sanctum), pour
    // la page d'achat en ligne. Rien de sensible sur Route/RouteStop.
    public function publicRoutes(): JsonResponse
    {
        $routes = Route::active()
            ->with('stops')
            ->orderBy('name')
            ->get()
            ->map(fn ($route) => [
                'id'                     => $route->id,
                'code'                   => $route->code,
                'name'                   => $route->name,
                'origin_city'            => $route->origin_city,
                'destination_city'       => $route->destination_city,
                'distance_km'            => $route->distance_km,
                'estimated_duration_min' => $route->estimated_duration_min,
                'base_fare'              => $route->base_fare,
                'is_dynamic'             => $route->is_dynamic,
                'stops'                  => $route->is_dynamic
                    ? $route->stops->map(fn ($s) => [
                        'id'               => $s->id,
                        'city_name'        => $s->city_name,
                        'stop_order'       => $s->stop_order,
                        'fare_from_origin' => $s->fare_from_origin,
                    ])->values()
                    : [],
            ]);

        return response()->json(['data' => $routes]);
    }

    // GET /api/v1/tickets/online/departures?route_id=&date= — public, départs
    // encore ouverts à la vente (places restantes, pas encore partis/annulés).
    public function publicDepartures(Request $request): JsonResponse
    {
        $request->validate([
            'route_id' => 'required|integer|exists:routes,id',
            'date'     => 'nullable|date',
        ]);

        $date = $request->filled('date') ? Carbon::parse($request->date) : Carbon::today();

        $departures = Departure::where('route_id', $request->integer('route_id'))
            ->notCancelled()
            ->forDate($date)
            ->whereIn('status', ['scheduled', 'boarding'])
            ->where('seats_available', '>', 0)
            ->orderBy('departure_datetime')
            ->get()
            ->map(fn (Departure $d) => [
                'id'                 => $d->id,
                'departure_datetime' => $d->departure_datetime?->toIso8601String(),
                'estimated_arrival'  => $d->estimated_arrival?->toIso8601String(),
                'seats_available'    => $d->seats_available,
                'boarding_gate'      => $d->boarding_gate,
            ]);

        return response()->json(['data' => $departures]);
    }

    // GET /api/v1/tickets/{ticket}
    public function show(Ticket $ticket): JsonResponse
    {
        $ticket->load(['departure.route', 'departure.vehicle', 'departure.driver', 'departure.gate', 'destinationStop', 'soldBy']);

        return response()->json($ticket);
    }

    // PATCH /api/v1/tickets/{ticket}/status
    public function updateStatus(UpdateTicketStatusRequest $request, Ticket $ticket): JsonResponse
    {
        try {
            $ticket = $this->ticketService->updateStatus(
                $ticket,
                $request->validated('status'),
                $request->validated(),
                $request->user()->id
            );

            return response()->json(['message' => 'Statut mis à jour', 'ticket' => $ticket]);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    // GET /api/v1/tickets/mine — mes ventes du jour (caissier, libre-service)
    public function mine(Request $request): JsonResponse
    {
        $date = $request->filled('date') ? Carbon::parse($request->date) : Carbon::today();

        $tickets = Ticket::with(['departure.route', 'departure.gate', 'destinationStop'])
            ->where('sold_by', $request->user()->id)
            ->whereDate('purchased_at', $date)
            ->latest('purchased_at')
            ->get();

        return response()->json([
            'data'    => $tickets,
            'summary' => [
                'count'         => $tickets->count(),
                'revenue_fcfa'  => (float) $tickets->where('status', '!=', 'cancelled')->sum('price_fcfa'),
            ],
        ]);
    }

    // POST /api/v1/tickets/scan — embarquement par scan QR ou saisie manuelle
    // de la référence (contrôleur, manager)
    public function scan(Request $request): JsonResponse
    {
        $request->validate(['reference' => 'required|string']);

        try {
            $ticket = $this->ticketService->scanBoard($request->string('reference'), $request->user()->id);

            return response()->json([
                'message' => "Billet {$ticket->reference} embarqué",
                'ticket'  => $ticket,
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    // GET /api/v1/tickets/scan/stats — statistiques d'embarquement du jour
    public function scanStats(Request $request): JsonResponse
    {
        return response()->json([
            'today_total'  => Ticket::where('status', 'boarded')->whereDate('boarded_at', today())->count(),
            'today_by_me'  => Ticket::where('status', 'boarded')->whereDate('boarded_at', today())
                ->where('boarded_by', $request->user()->id)->count(),
        ]);
    }

    // GET /api/v1/tickets/departure/{departure}/manifest — liste d'embarquement
    public function manifest(Departure $departure): JsonResponse
    {
        $tickets = $departure->tickets()
            ->with('destinationStop')
            ->active()
            ->orderByRaw('seat_number IS NULL, seat_number')
            ->orderBy('purchased_at')
            ->get();

        return response()->json([
            'departure' => $departure->load('route', 'vehicle', 'gate'),
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
