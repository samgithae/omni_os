<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreClassifiedReplyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Auth handled by middleware
    }

    public function rules(): array
    {
        return [
            'email_message_id' => ['required', 'integer', 'exists:email_messages,id'],
            'lead_id' => ['required', 'integer', 'exists:leads,id'],
            'classification' => ['required', 'string', 'in:interested,not_interested,out_of_office,unsubscribe,bounce'],
            'summary' => ['required', 'string', 'max:2000'],
            'reply_body' => ['nullable', 'string', 'max:10000'],
            'confidence' => ['nullable', 'numeric', 'min:0', 'max:1'],
        ];
    }

    public function messages(): array
    {
        return [
            'classification.in' => 'Classification must be one of: interested, not_interested, out_of_office, unsubscribe, bounce.',
        ];
    }
}
