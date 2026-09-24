<?php

namespace App\Http\Requests;

use App\Character\CharacterAccess;
use App\Enums\CharacterType;
use App\Models\Character;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        if (! $this->filled('character_id')) {
            return true;
        }

        $character = Character::query()->find($this->integer('character_id'));

        if ($character === null) {
            return true;
        }

        return CharacterAccess::canSpeakAs($this->user(), $character);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'max:4000'],
            'npc_name' => ['prohibited'],
            'character_id' => ['sometimes', 'nullable', 'integer', 'exists:characters,id'],
            'scene_id' => ['sometimes', 'integer', 'exists:scenes,id'],
            'chronicle_id' => ['sometimes', 'integer', 'exists:chronicles,id'],
            'copilot_request_id' => [
                'sometimes',
                'nullable',
                'integer',
                'exists:copilot_requests,id',
                'required_with:copilot_draft_index',
                Rule::prohibitedIf(fn (): bool => ! $this->isNpcSpeech()),
            ],
            'copilot_draft_index' => [
                'sometimes',
                'nullable',
                'integer',
                'min:0',
                'required_with:copilot_request_id',
                Rule::prohibitedIf(fn (): bool => ! $this->isNpcSpeech()),
            ],
        ];
    }

    private function isNpcSpeech(): bool
    {
        if (! $this->filled('character_id')) {
            return false;
        }

        $type = Character::query()
            ->whereKey($this->integer('character_id'))
            ->value('character_type');

        return $type === CharacterType::Npc
            || $type === CharacterType::Npc->value;
    }
}
