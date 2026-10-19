<?php

namespace App\Http\Requests;

use App\Models\Chronicle;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RollDiceRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $chronicle = $this->route('chronicle');
        $isStoryteller = $chronicle instanceof Chronicle
            && $this->user() !== null
            && $this->user()->isStorytellerIn($chronicle);

        return [
            'pool' => ['required', 'integer', 'min:1', 'max:100'],
            'difficulty' => ['required', 'integer', 'min:1', 'max:20'],
            'dice_type' => ['sometimes', 'integer', Rule::in([4, 6, 8, 10, 12, 20])],
            'character_id' => ['sometimes', 'nullable', 'integer', 'exists:characters,id'],
            'is_hidden' => [
                'sometimes',
                'boolean',
                Rule::prohibitedIf(fn (): bool => $this->boolean('is_hidden') && ! $isStoryteller),
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (! $this->has('dice_type')) {
            $this->merge(['dice_type' => 10]);
        }

        if (! $this->has('is_hidden')) {
            $this->merge(['is_hidden' => false]);
        }
    }
}
