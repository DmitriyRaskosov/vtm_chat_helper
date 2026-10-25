<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GenerateSceneEventRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'action_description' => ['required', 'string', 'max:4000'],
            'attack_successes' => ['required', 'integer', 'min:0', 'max:100'],
            'attack_botch' => ['sometimes', 'boolean'],
            'defense_successes' => ['required', 'integer', 'min:0', 'max:100'],
            'defense_botch' => ['sometimes', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (! $this->has('attack_botch')) {
            $this->merge(['attack_botch' => false]);
        }

        if (! $this->has('defense_botch')) {
            $this->merge(['defense_botch' => false]);
        }
    }
}
