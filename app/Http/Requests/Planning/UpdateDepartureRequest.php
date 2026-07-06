<?php
// ══════════════════════════════════════════════════════════════════════════
// app/Http/Requests/Planning/UpdateDepartureRequest.php
// ══════════════════════════════════════════════════════════════════════════
namespace App\Http\Requests\Planning;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDepartureRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'departure_datetime' => 'sometimes|date',
            'estimated_arrival'  => 'sometimes|date',
            'vehicle_id'         => 'nullable|exists:vehicles,id',
            'driver_id'          => 'nullable|exists:drivers,id',
            'boarding_gate_id'   => 'nullable|exists:boarding_gates,id',
            'seats_available'    => 'nullable|integer|min:0',
            'notes'              => 'nullable|string|max:500',
            'actual_departure'   => 'nullable|date',
            'actual_arrival'     => 'nullable|date',
        ];
    }
}
