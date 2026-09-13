<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreWorldFactionRelationRequest extends FormRequest
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
            'source_entity_id' => ['required', 'integer', 'exists:world_entities,id'],
            'target_entity_id' => ['required', 'integer', 'different:source_entity_id', 'exists:world_entities,id'],
            'relation_key' => ['required', Rule::in(['hostile_to', 'allied_with'])],
            'note' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'chronicle_id' => ['sometimes', 'integer', 'exists:chronicles,id'],
        ];
    }
}
