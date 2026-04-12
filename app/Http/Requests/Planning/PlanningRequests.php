<?php
// ══════════════════════════════════════════════════════════════════════════
// StoreDepartureRequest.php
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


// ══════════════════════════════════════════════════════════════════════════
// UpdateDepartureStatusRequest.php
// ══════════════════════════════════════════════════════════════════════════
namespace App\Http\Requests\Planning;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDepartureStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status'              => 'required|in:boarding,departed,arrived,cancelled',
            'actual_departure'    => 'nullable|date|required_if:status,departed',
            'actual_arrival'      => 'nullable|date|required_if:status,arrived',
            'cancellation_reason' => 'nullable|string|min:10|required_if:status,cancelled',
        ];
    }
}


// ══════════════════════════════════════════════════════════════════════════
// StoreScheduleTemplateRequest.php
// ══════════════════════════════════════════════════════════════════════════
namespace App\Http\Requests\Planning;

use Illuminate\Foundation\Http\FormRequest;

class StoreScheduleTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'route_id'       => 'required|exists:routes,id',
            'departure_time' => 'required|date_format:H:i',
            'days_of_week'   => 'required|array|min:1',
            'days_of_week.*' => 'integer|between:0,6',
            'valid_from'     => 'required|date',
            'valid_until'    => 'nullable|date|after:valid_from',
        ];
    }

    public function messages(): array
    {
        return [
            'days_of_week.required'  => 'Au moins un jour de semaine est requis',
            'days_of_week.*.between' => 'Les jours doivent être entre 0 (dim) et 6 (sam)',
        ];
    }
}
