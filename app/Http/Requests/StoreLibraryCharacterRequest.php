<?php

namespace App\Http\Requests;

use App\Enums\CharacterType;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLibraryCharacterRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if ($this->actingAsStoryteller() || $this->filled('character_id')) {
            return;
        }

        $this->replace($this->except(['character_type']));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $isStoryteller = $this->actingAsStoryteller();

        return [
            'character_id' => [
                'prohibits:name,clan_id,concept,character_type',
                'required_without:name',
                'integer',
                'exists:characters,id',
            ],
            'name' => ['required_without:character_id', 'string', 'max:100'],
            'clan_id' => $isStoryteller
                ? ['exclude_with:character_id', 'nullable', 'integer', 'exists:canon_clans,id']
                : ['exclude_with:character_id', 'required_without:character_id', 'integer', 'exists:canon_clans,id'],
            'concept' => ['exclude_with:character_id', 'nullable', 'string', 'max:120'],
            'character_type' => $isStoryteller
                ? ['exclude_with:character_id', 'required_without:character_id', Rule::enum(CharacterType::class)]
                : ['exclude'],
        ];
    }

    private function actingAsStoryteller(): bool
    {
        $user = $this->user();

        return $user instanceof User && $user->isStorytellerAnywhere();
    }
}
