<?php

namespace App\Http\Requests;

use App\Enums\CharacterMeritKind;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCharacterMeritsRequest extends FormRequest
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
            'merits_flaws' => ['present', 'array'],
            'merits_flaws.*.kind' => ['required', Rule::enum(CharacterMeritKind::class)],
            'merits_flaws.*.name' => ['required', 'string', 'max:160'],
            'merits_flaws.*.cost' => ['required', 'integer', 'min:0', 'max:10'],
            'merits_flaws.*.note' => ['sometimes', 'nullable', 'string'],
        ];
    }
}
