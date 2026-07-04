<?php
// ══════════════════════════════════════════════════════════════════════════
// app/Http/Requests/Planning/StoreDepartureRequest.php
// ══════════════════════════════════════════════════════════════════════════
namespace App\Http\Requests\Planning;

use Illuminate\Foundation\Http\FormRequest;

class StoreDepartureRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Remplacer par: $this->user()->hasRole('manager')
    }

    public function rules(): array
    {
        return [
            'route_id'           => 'required|exists:routes,id',
            'vehicle_id'         => 'nullable|exists:vehicles,id',
            'driver_id'          => 'nullable|exists:drivers,id',
            'departure_datetime' => 'required|date|after:now',
            'estimated_arrival'  => 'required|date|after:departure_datetime',
            'seats_available'    => 'nullable|integer|min:0',
            'notes'              => 'nullable|string|max:500',
        ];
    }

    public function messages(): array
    {
        return [
            'route_id.required'           => 'La ligne est obligatoire',
            'departure_datetime.required' => 'La date et heure de départ sont obligatoires',
            'departure_datetime.after'    => 'Le départ doit être dans le futur',
            'estimated_arrival.after'     => 'L\'arrivée doit être après le départ',
        ];
    }
}
