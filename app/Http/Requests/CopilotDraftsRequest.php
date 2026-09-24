<?php

namespace App\Http\Requests;

use App\Enums\CharacterType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CopilotDraftsRequest extends FormRequest
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
            'character_id' => [
                'required',
                'integer',
                Rule::exists('characters', 'id')->where(function ($query): void {
                    $query->where('character_type', CharacterType::Npc->value)
                        ->where('is_active', true);
                }),
            ],
            'prompt' => ['required', 'string', 'max:2000'],
            'scene_id' => ['sometimes', 'integer', 'exists:scenes,id'],
            'chronicle_id' => ['sometimes', 'integer', 'exists:chronicles,id'],
        ];
    }
}
