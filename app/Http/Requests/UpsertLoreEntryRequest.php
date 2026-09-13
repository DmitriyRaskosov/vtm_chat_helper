<?php

namespace App\Http\Requests;

use App\Enums\LoreAccessLevel;
use App\Enums\LoreEntryKind;
use App\Enums\LoreVisibility;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpsertLoreEntryRequest extends FormRequest
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
            'kind' => ['sometimes', Rule::enum(LoreEntryKind::class)],
            'canonical_text' => ['required', 'string', 'max:50000'],
            'visibility' => ['sometimes', Rule::enum(LoreVisibility::class)],
            'classification' => ['sometimes', Rule::enum(LoreAccessLevel::class)],
            'situational' => ['sometimes', 'boolean'],
            'entity_ids' => ['sometimes', 'array'],
            'entity_ids.*' => ['integer', 'exists:world_entities,id'],
            'granted_character_ids' => ['sometimes', 'array'],
            'granted_character_ids.*' => ['integer', 'exists:characters,id'],
            'denied_character_ids' => ['sometimes', 'array'],
            'denied_character_ids.*' => ['integer', 'exists:characters,id'],
            'chronicle_id' => ['sometimes', 'integer', 'exists:chronicles,id'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $grantedInput = $this->input('granted_character_ids', []);
            $deniedInput = $this->input('denied_character_ids', []);
            $granted = is_array($grantedInput) ? array_map('intval', $grantedInput) : [];
            $denied = is_array($deniedInput) ? array_map('intval', $deniedInput) : [];
            if (array_intersect($granted, $denied) !== []) {
                $validator->errors()->add(
                    'granted_character_ids',
                    'A character cannot be both granted and denied the same lore entry.',
                );
            }
        });
    }
}
