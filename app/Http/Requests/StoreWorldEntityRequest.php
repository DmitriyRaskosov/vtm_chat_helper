<?php

namespace App\Http\Requests;

use App\Enums\ConceptType;
use App\Enums\FactionType;
use App\Enums\ItemType;
use App\Enums\LocationType;
use App\Enums\WorldEntityType;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreWorldEntityRequest extends FormRequest
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
            'canonical_name' => ['required', 'string', 'max:120'],
            'entity_type' => ['required', Rule::in([
                WorldEntityType::Faction->value,
                WorldEntityType::Location->value,
                WorldEntityType::Item->value,
                WorldEntityType::Concept->value,
            ])],
            'short_description' => ['sometimes', 'nullable', 'string', 'max:4000'],
            'subtype' => ['sometimes', 'nullable', 'string', 'max:32'],
            'aliases' => ['sometimes', 'array'],
            'aliases.*' => ['string', 'max:120'],
            'parent_faction_id' => ['sometimes', 'nullable', 'integer', 'exists:factions,id'],
            'chronicle_id' => ['sometimes', 'integer', 'exists:chronicles,id'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $subtype = $this->input('subtype');
            if (is_string($subtype) && trim($subtype) !== '') {
                $ok = match ($this->input('entity_type')) {
                    WorldEntityType::Faction->value => FactionType::tryFrom($subtype) !== null,
                    WorldEntityType::Location->value => LocationType::tryFrom($subtype) !== null,
                    WorldEntityType::Item->value => ItemType::tryFrom($subtype) !== null,
                    WorldEntityType::Concept->value => ConceptType::tryFrom($subtype) !== null,
                    default => false,
                };

                if (! $ok) {
                    $validator->errors()->add('subtype', 'Invalid subtype for this entity type.');
                }
            }

            if ($this->filled('parent_faction_id') && $this->input('entity_type') !== WorldEntityType::Faction->value) {
                $validator->errors()->add('parent_faction_id', 'Only factions can have a parent faction.');
            }
        });
    }
}
