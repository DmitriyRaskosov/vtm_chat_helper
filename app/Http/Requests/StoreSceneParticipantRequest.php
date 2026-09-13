<?php

namespace App\Http\Requests;

use App\Enums\SceneParticipantRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSceneParticipantRequest extends FormRequest
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
            'character_id' => ['required', 'integer', 'exists:characters,id'],
            'role' => ['required', 'string', Rule::enum(SceneParticipantRole::class)],
            'visible' => ['sometimes', 'boolean'],
        ];
    }
}
