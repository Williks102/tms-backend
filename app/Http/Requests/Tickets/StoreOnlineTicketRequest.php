<?php
// ══════════════════════════════════════════════════════════════════════════
// app/Http/Requests/Tickets/StoreOnlineTicketRequest.php — Achat en ligne
// Endpoint public (hors auth:sanctum) — simule la billetterie web/mobile.
// ══════════════════════════════════════════════════════════════════════════
namespace App\Http\Requests\Tickets;

use App\Services\Payments\PaiementProService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            // Exigé par PaiementPro (customerEmail) — pas seulement utile pour un reçu.
            'passenger_email'      => 'required|email|max:150',
            'seat_number'          => 'nullable|string|max:10',
            // Opérateur choisi pour le paiement — payment_method reste fixé
            // à 'online' côté serveur (voir TicketService::issue()), jamais
            // fourni par le client.
            'payment_channel'      => ['required', Rule::in(PaiementProService::CHANNELS)],
            // Jamais de price_fcfa ici — canal public, tarif toujours recalculé
            // serveur dans TicketService::issue() (voir runbook sécurité, point 2).
        ];
    }

    public function messages(): array
    {
        return [
            'departure_id.required'    => 'Le départ est obligatoire',
            'departure_id.exists'      => 'Ce départ n\'existe pas',
            'passenger_name.required'  => 'Le nom du passager est obligatoire',
            'passenger_phone.required' => 'Le téléphone est obligatoire pour la confirmation en ligne',
            'passenger_email.required' => 'L\'email est obligatoire pour le paiement en ligne',
            'payment_channel.required' => 'Le moyen de paiement est obligatoire',
            'payment_channel.in'       => 'Moyen de paiement inconnu',
        ];
    }
}
