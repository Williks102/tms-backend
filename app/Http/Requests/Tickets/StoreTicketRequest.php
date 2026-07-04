<?php
// ══════════════════════════════════════════════════════════════════════════
// app/Http/Requests/Tickets/StoreTicketRequest.php — Vente au guichet
// ══════════════════════════════════════════════════════════════════════════
namespace App\Http\Requests\Tickets;

use Illuminate\Foundation\Http\FormRequest;

class StoreTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Autorisation gérée par le middleware role: sur la route
    }

    public function rules(): array
    {
        return [
            'departure_id'    => 'required|exists:departures,id',
            'passenger_name'  => 'required|string|max:150',
            'passenger_phone' => 'nullable|string|max:30',
            'seat_number'     => 'nullable|string|max:10',
            'payment_method'  => 'required|in:cash,mobile_money,card',
            'price_fcfa'      => 'nullable|numeric|min:0',
        ];
    }

    public function messages(): array
    {
        return [
            'departure_id.required'   => 'Le départ est obligatoire',
            'departure_id.exists'     => 'Ce départ n\'existe pas',
            'passenger_name.required' => 'Le nom du passager est obligatoire',
        ];
    }
}
