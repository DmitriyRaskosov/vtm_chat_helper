<?php

namespace App\Http\Requests;

use App\Enums\DiceRollMode;
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

        $mode = $this->input('mode', DiceRollMode::Successes->value);

        return [
            'pool' => ['required', 'integer', 'min:1', 'max:100'],
            'difficulty' => [
                Rule::requiredIf($mode === DiceRollMode::Successes->value),
                'nullable',
                'integer',
                'min:1',
                'max:20',
            ],
            'mode' => ['sometimes', 'string', Rule::enum(DiceRollMode::class)],
            'dice_type' => ['sometimes', 'integer', Rule::in([4, 6, 8, 10, 12, 20])],
            'character_id' => ['sometimes', 'nullable', 'integer', 'exists:characters,id'],
            'comment' => ['sometimes', 'nullable', 'string', 'max:200'],
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

        if (! $this->has('mode')) {
            $this->merge(['mode' => DiceRollMode::Successes->value]);
        }

        if ($this->input('mode') === DiceRollMode::Sum->value && ! $this->has('difficulty')) {
            $this->merge(['difficulty' => 6]);
        }
    }
}
