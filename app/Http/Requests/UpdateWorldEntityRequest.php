<?php

namespace App\Http\Requests;

use App\Enums\ConceptType;
use App\Enums\FactionType;
use App\Enums\ItemType;
use App\Enums\LocationType;
use App\Enums\WorldEntityType;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class UpdateWorldEntityRequest extends FormRequest
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
            'short_description' => ['sometimes', 'nullable', 'string', 'max:4000'],
            'subtype' => ['sometimes', 'nullable', 'string', 'max:32'],
            'entity_type' => ['prohibited'],
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
            if (! is_string($subtype) || trim($subtype) === '') {
                return;
            }

            $entity = $this->route('worldEntity');
            $entityType = $entity?->entity_type instanceof WorldEntityType
                ? $entity->entity_type->value
                : (string) ($entity?->entity_type ?? '');

            $ok = match ($entityType) {
                WorldEntityType::Faction->value => FactionType::tryFrom($subtype) !== null,
                WorldEntityType::Location->value => LocationType::tryFrom($subtype) !== null,
                WorldEntityType::Item->value => ItemType::tryFrom($subtype) !== null,
                WorldEntityType::Concept->value => ConceptType::tryFrom($subtype) !== null,
                default => false,
            };

            if (! $ok) {
                $validator->errors()->add('subtype', 'Invalid subtype for this entity type.');
            }
        });
    }
}
