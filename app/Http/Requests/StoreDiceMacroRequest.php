<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDiceMacroRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'pool' => ['required', 'integer', 'min:1', 'max:100'],
            'difficulty' => ['required', 'integer', 'min:1', 'max:20'],
            'dice_type' => ['sometimes', 'integer', Rule::in([4, 6, 8, 10, 12, 20])],
            'use_willpower' => ['sometimes', 'boolean'],
            'stats' => ['sometimes', 'array'],
            'stats.*' => ['string', 'max:64'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (! $this->has('dice_type')) {
            $this->merge(['dice_type' => 10]);
        }

        if (! $this->has('use_willpower')) {
            $this->merge(['use_willpower' => false]);
        }

        if (! $this->has('stats')) {
            $this->merge(['stats' => []]);
        }
    }
}
