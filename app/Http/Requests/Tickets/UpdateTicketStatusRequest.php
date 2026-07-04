<?php
// ══════════════════════════════════════════════════════════════════════════
// app/Http/Requests/Tickets/UpdateTicketStatusRequest.php
// ══════════════════════════════════════════════════════════════════════════
namespace App\Http\Requests\Tickets;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTicketStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status'               => 'required|in:boarded,cancelled,refunded',
            'cancellation_reason'  => 'nullable|string|min:5|required_if:status,cancelled,refunded',
        ];
    }

    public function messages(): array
    {
        return [
            'cancellation_reason.required_if' => 'Le motif est obligatoire pour une annulation ou un remboursement',
        ];
    }
}
