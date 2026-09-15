<?php

namespace App\Http\Requests;

use App\Enums\WorldEntityType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class PatchExtractionCandidateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'candidate_type' => ['required', 'string', Rule::in(['mention'])],
            'name' => ['sometimes', 'string', 'min:1', 'max:255'],
            'kind' => ['sometimes', 'string', Rule::in([
                WorldEntityType::Faction->value,
                WorldEntityType::Clan->value,
                WorldEntityType::Coterie->value,
                WorldEntityType::Circle->value,
                WorldEntityType::Other->value,
                WorldEntityType::Location->value,
                WorldEntityType::Item->value,
                WorldEntityType::Concept->value,
            ])],
            'aliases' => ['sometimes', 'array'],
            'aliases.*' => ['string', 'max:120'],
            'alias_of_entity_id' => ['sometimes', 'nullable', 'integer', 'exists:world_entities,id'],
            'sect_faction_id' => ['sometimes', 'nullable', 'integer', 'exists:factions,id'],
            'chronicle_id' => ['sometimes', 'integer', 'exists:chronicles,id'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (! $this->hasAny(['name', 'kind', 'aliases', 'alias_of_entity_id', 'sect_faction_id'])) {
                $validator->errors()->add('patch', 'At least one of name, kind, aliases, alias_of_entity_id, or sect_faction_id is required.');
            }
        });
    }
}
