<?php

namespace App\Http\Requests;

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
            'aliases' => ['sometimes', 'array'],
            'aliases.*' => ['string', 'max:120'],
            'parent_faction_id' => ['sometimes', 'nullable', 'integer', 'exists:factions,id'],
            'sect_faction_id' => ['sometimes', 'nullable', 'integer', 'exists:factions,id'],
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

            $entity = $this->route('worldEntity');
            $entityType = $entity?->entity_type instanceof WorldEntityType
                ? $entity->entity_type->value
                : (string) ($entity?->entity_type ?? '');

            if ($this->filled('parent_faction_id')) {
                if ($entityType !== WorldEntityType::Faction->value) {
                    $validator->errors()->add('parent_faction_id', 'Only factions can have a parent faction.');
                } elseif ($entity !== null && (int) $this->input('parent_faction_id') === (int) $entity->id) {
                    $validator->errors()->add('parent_faction_id', 'A faction cannot be its own parent.');
                }
            }

            if ($this->filled('sect_faction_id')
                && ! in_array($entityType, [
                    WorldEntityType::Coterie->value,
                    WorldEntityType::Circle->value,
                ], true)) {
                $validator->errors()->add('sect_faction_id', 'Only coteries and circles can have a sect faction.');
            }
        });
    }
}
