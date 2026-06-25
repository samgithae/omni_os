<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreActivityEventCommentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = [
            'body' => ['required', 'string', 'max:10000'],
            'is_instruction' => ['nullable', 'boolean'],
        ];

        // API path allows hermes/agent author
        if ($this->is('api/*')) {
            $rules['author'] = ['required', 'string', 'in:hermes,agent'];
            $rules['metadata'] = ['sometimes', 'array'];
        }

        return $rules;
    }
}
