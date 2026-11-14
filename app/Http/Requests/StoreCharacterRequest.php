<?php

namespace App\Http\Requests;

use App\Enums\CharacterType;
use App\Models\Chronicle;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCharacterRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if ($this->actingAsStoryteller()) {
            return;
        }

        $this->replace($this->except(['user_id', 'character_type']));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $isStoryteller = $this->actingAsStoryteller();

        return [
            'canonical_name' => ['required', 'string', 'max:100'],
            'character_type' => $isStoryteller
                ? ['required', Rule::enum(CharacterType::class)]
                : ['exclude'],
            'chronicle_id' => ['prohibited'],
            'user_id' => $isStoryteller
                ? [
                    Rule::requiredIf(fn (): bool => $this->enum('character_type', CharacterType::class) === CharacterType::Player),
                    'nullable',
                    'integer',
                    'exists:users,id',
                ]
                : ['prohibited'],
            'clan_id' => $isStoryteller
                ? ['sometimes', 'nullable', 'integer', 'exists:canon_clans,id']
                : ['required', 'integer', 'exists:canon_clans,id'],
            'sire_character_id' => ['sometimes', 'nullable', 'integer', 'exists:characters,id'],
            'generation' => ['sometimes', 'nullable', 'integer', 'min:4', 'max:15'],
            'nature' => ['sometimes', 'nullable', 'string', 'max:64'],
            'demeanor' => ['sometimes', 'nullable', 'string', 'max:64'],
            'concept' => ['sometimes', 'nullable', 'string', 'max:120'],
        ];
    }

    private function actingAsStoryteller(): bool
    {
        $user = $this->user();
        $chronicle = $this->routeChronicle();

        return $user !== null
            && $chronicle !== null
            && $user->isStorytellerIn($chronicle);
    }

    private function routeChronicle(): ?Chronicle
    {
        $chronicle = $this->route('chronicle');

        if ($chronicle instanceof Chronicle) {
            return $chronicle;
        }

        if (is_string($chronicle) && ctype_digit($chronicle)) {
            return Chronicle::query()->find((int) $chronicle);
        }

        return null;
    }
}
