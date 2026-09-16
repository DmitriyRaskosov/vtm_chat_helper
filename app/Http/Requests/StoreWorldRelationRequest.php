<?php

namespace App\Http\Requests;

use App\World\WorldRelationTypeCatalog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreWorldRelationRequest extends FormRequest
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
        $keys = array_map(
            fn (array $definition): string => $definition['key'],
            WorldRelationTypeCatalog::definitions(),
        );

        return [
            'source_entity_id' => ['required', 'integer', 'exists:world_entities,id'],
            'target_entity_id' => ['required', 'integer', 'different:source_entity_id', 'exists:world_entities,id'],
            'relation_key' => ['required', Rule::in($keys)],
            'note' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'intensity' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:5'],
            'metadata' => ['sometimes', 'nullable', 'array'],
            'chronicle_id' => ['sometimes', 'integer', 'exists:chronicles,id'],
        ];
    }
}
