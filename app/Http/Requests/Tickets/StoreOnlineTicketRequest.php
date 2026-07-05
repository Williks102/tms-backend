<?php
// ══════════════════════════════════════════════════════════════════════════
// app/Http/Requests/Tickets/StoreOnlineTicketRequest.php — Achat en ligne
// Endpoint public (hors auth:sanctum) — simule la billetterie web/mobile.
// ══════════════════════════════════════════════════════════════════════════
namespace App\Http\Requests\Tickets;

use Illuminate\Foundation\Http\FormRequest;

class StoreOnlineTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'departure_id'         => 'required|exists:departures,id',
            'destination_stop_id'  => 'nullable|integer|exists:route_stops,id',
            'passenger_name'       => 'required|string|max:150',
            'passenger_phone'      => 'required|string|max:30',
            'seat_number'          => 'nullable|string|max:10',
            'payment_method'       => 'required|in:mobile_money,card,online',
            'price_fcfa'           => 'nullable|numeric|min:0',
        ];
    }

    public function messages(): array
    {
        return [
            'departure_id.required'    => 'Le départ est obligatoire',
            'departure_id.exists'      => 'Ce départ n\'existe pas',
            'passenger_name.required'  => 'Le nom du passager est obligatoire',
            'passenger_phone.required' => 'Le téléphone est obligatoire pour la confirmation en ligne',
        ];
    }
}
