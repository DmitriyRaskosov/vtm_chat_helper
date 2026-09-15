<?php

namespace App\Http\Requests;

use App\Enums\WorldEventType;
use App\Enums\WorldEventVisibility;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreWorldEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->isStoryteller();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:200'],
            'description' => ['sometimes', 'nullable', 'string', 'max:8000'],
            'summary' => ['sometimes', 'nullable', 'string', 'max:8000'],
            'scene_id' => ['sometimes', 'nullable', 'integer', 'exists:scenes,id'],
            'event_type' => ['sometimes', 'string', Rule::enum(WorldEventType::class)],
            'importance' => ['sometimes', 'integer', 'min:0', 'max:5'],
            'visibility' => ['sometimes', 'string', Rule::enum(WorldEventVisibility::class)],
            'chronicle_id' => ['sometimes', 'integer', 'exists:chronicles,id'],
        ];
    }
}
