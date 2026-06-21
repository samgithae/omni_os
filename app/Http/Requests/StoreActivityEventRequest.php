<?php

namespace App\Http\Requests;

use App\Enums\ActivityEventType;
use App\Enums\ActivitySeverity;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class StoreActivityEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Auth handled by middleware
    }

    public function rules(): array
    {
        return [
            'brand_slug' => ['nullable', 'string', 'exists:brands,slug'],
            'source' => ['required', 'string', 'max:255'],
            'event_type' => ['required', new Enum(ActivityEventType::class)],
            'title' => ['required', 'string', 'max:500'],
            'body' => ['nullable', 'string'],
            'metadata' => ['nullable', 'array'],
            'severity' => ['required', new Enum(ActivitySeverity::class)],
            'notify_telegram' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'event_type.Illuminate\Validation\Rules\Enum' => 'Invalid event type. Must be one of: ' . implode(', ', array_map(fn($c) => $c->value, ActivityEventType::cases())),
            'severity.Illuminate\Validation\Rules\Enum' => 'Invalid severity. Must be one of: ' . implode(', ', array_map(fn($c) => $c->value, ActivitySeverity::cases())),
        ];
    }
}
