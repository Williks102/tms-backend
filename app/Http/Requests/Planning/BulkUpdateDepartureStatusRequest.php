<?php
// ══════════════════════════════════════════════════════════════════════════
// app/Http/Requests/Planning/BulkUpdateDepartureStatusRequest.php
// ══════════════════════════════════════════════════════════════════════════
namespace App\Http\Requests\Planning;

use Illuminate\Foundation\Http\FormRequest;

class BulkUpdateDepartureStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'departure_ids'   => 'required|array|min:1',
            'departure_ids.*' => 'integer|exists:departures,id',
            'status'          => 'required|in:boarding,departed,arrived,cancelled',
        ];
    }
}
