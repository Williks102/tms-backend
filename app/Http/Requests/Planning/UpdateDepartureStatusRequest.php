<?php
// ══════════════════════════════════════════════════════════════════════════
// app/Http/Requests/Planning/UpdateDepartureStatusRequest.php
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
