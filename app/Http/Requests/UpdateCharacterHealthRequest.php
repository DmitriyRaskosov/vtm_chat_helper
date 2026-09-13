<?php

namespace App\Http\Requests;

use App\Enums\CharacterHealthDamage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCharacterHealthRequest extends FormRequest
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
            'boxes' => ['required', 'array', 'max:7'],
            'boxes.*.index' => ['required', 'integer', 'min:0', 'max:6'],
            'boxes.*.damage' => ['nullable', Rule::enum(CharacterHealthDamage::class)],
        ];
    }
}
