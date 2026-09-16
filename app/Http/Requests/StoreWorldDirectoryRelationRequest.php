<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreWorldDirectoryRelationRequest extends FormRequest
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
            'relation_key' => ['required', Rule::in(['controls', 'owns', 'part_of'])],
            'chronicle_id' => ['sometimes', 'integer', 'exists:chronicles,id'],
        ];
    }
}
