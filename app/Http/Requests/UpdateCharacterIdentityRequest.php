<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCharacterIdentityRequest extends FormRequest
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
            'canonical_name' => ['sometimes', 'string', 'max:120'],
            'clan_entity_id' => ['sometimes', 'nullable', 'integer', 'exists:world_entities,id'],
            'sire_character_id' => ['sometimes', 'nullable', 'integer', 'exists:characters,id'],
            'generation' => ['sometimes', 'nullable', 'integer', 'min:4', 'max:15'],
            'nature' => ['sometimes', 'nullable', 'string', 'max:64'],
            'demeanor' => ['sometimes', 'nullable', 'string', 'max:64'],
            'concept' => ['sometimes', 'nullable', 'string', 'max:120'],
        ];
    }
}
