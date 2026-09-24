<?php

namespace App\Http\Requests;

use App\Enums\CharacterType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCharacterRequest extends FormRequest
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
            'character_type' => ['required', Rule::enum(CharacterType::class)],
            'chronicle_id' => ['sometimes', 'integer', 'exists:chronicles,id'],
            'user_id' => ['required_if:character_type,player', 'nullable', 'integer', 'exists:users,id'],
            'clan_id' => ['sometimes', 'nullable', 'integer', 'exists:canon_clans,id'],
            'sire_character_id' => ['sometimes', 'nullable', 'integer', 'exists:characters,id'],
            'generation' => ['sometimes', 'nullable', 'integer', 'min:4', 'max:15'],
            'nature' => ['sometimes', 'nullable', 'string', 'max:64'],
            'demeanor' => ['sometimes', 'nullable', 'string', 'max:64'],
            'concept' => ['sometimes', 'nullable', 'string', 'max:120'],
        ];
    }
}
