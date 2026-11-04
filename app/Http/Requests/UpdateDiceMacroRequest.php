<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDiceMacroRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'pool' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'difficulty' => ['sometimes', 'integer', 'min:1', 'max:20'],
            'dice_type' => ['sometimes', 'integer', Rule::in([4, 6, 8, 10, 12, 20])],
            'use_willpower' => ['sometimes', 'boolean'],
            'stats' => ['sometimes', 'array'],
            'stats.*' => ['string', 'max:64'],
        ];
    }
}
