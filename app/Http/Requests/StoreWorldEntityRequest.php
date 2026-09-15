<?php

namespace App\Http\Requests;

use App\Enums\WorldEntityType;
use App\World\WorldEntityService;
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
            'entity_type' => ['required', Rule::in(array_map(
                fn (WorldEntityType $type): string => $type->value,
                WorldEntityService::DIRECTORY_TYPES,
            ))],
            'short_description' => ['sometimes', 'nullable', 'string', 'max:4000'],
            'aliases' => ['sometimes', 'array'],
            'aliases.*' => ['string', 'max:120'],
            'parent_faction_id' => ['sometimes', 'nullable', 'integer', 'exists:factions,id'],
            'sect_faction_id' => ['sometimes', 'nullable', 'integer', 'exists:factions,id'],
            'chronicle_id' => ['sometimes', 'integer', 'exists:chronicles,id'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $entityType = $this->input('entity_type');

            if ($this->filled('parent_faction_id') && $entityType !== WorldEntityType::Faction->value) {
                $validator->errors()->add('parent_faction_id', 'Only factions can have a parent faction.');
            }

            if ($this->filled('sect_faction_id')
                && ! in_array($entityType, [
                    WorldEntityType::Clan->value,
                    WorldEntityType::Coterie->value,
                    WorldEntityType::Circle->value,
                ], true)) {
                $validator->errors()->add('sect_faction_id', 'Only clans, coteries, and circles can have a sect faction.');
            }
        });
    }
}
