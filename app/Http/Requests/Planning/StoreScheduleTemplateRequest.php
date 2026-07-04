<?php
// ══════════════════════════════════════════════════════════════════════════
// app/Http/Requests/Planning/StoreScheduleTemplateRequest.php
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
