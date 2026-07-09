<?php

namespace App\Http\Controllers;

use App\Http\Resources\Colis\ParcelTrackResource;
use App\Models\Parcel;
use App\Models\Ticket;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Compte client léger — pas de mot de passe, le numéro de téléphone est le
 * seul "secret" (même principe déjà accepté pour /suivi-colis). Public,
 * hors auth:sanctum, throttlé comme les autres endpoints publics.
 */
class MyPurchasesController extends Controller
{
    // GET /api/v1/mes-achats?phone=
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate(['phone' => 'required|string|min:6|max:30']);
        $phone = $data['phone'];

        $tickets = Ticket::with('departure.route')
            ->where('passenger_phone', $phone)
            ->latest('purchased_at')
            ->limit(50)
            ->get()
            ->map(fn (Ticket $t) => [
                'reference'          => $t->reference,
                'status'             => $t->status,
                'channel'            => $t->channel,
                'price_fcfa'         => $t->price_fcfa,
                'seat_number'        => $t->seat_number,
                'purchased_at'       => $t->purchased_at?->toIso8601String(),
                'route'              => $t->departure?->route ? [
                    'code'             => $t->departure->route->code,
                    'origin_city'      => $t->departure->route->origin_city,
                    'destination_city' => $t->departure->route->destination_city,
                ] : null,
                'departure_datetime' => $t->departure?->departure_datetime?->toIso8601String(),
            ]);

        $parcels = Parcel::with('departure.route')
            ->where(fn ($q) => $q->where('sender_phone', $phone)->orWhere('recipient_phone', $phone))
            ->latest('registered_at')
            ->limit(50)
            ->get();

        return response()->json([
            'tickets' => $tickets,
            'parcels' => ParcelTrackResource::collection($parcels),
        ]);
    }
}
