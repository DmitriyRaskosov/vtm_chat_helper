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
            'attack_willpower' => ['sometimes', 'boolean'],
            'defense_successes' => ['required', 'integer', 'min:0', 'max:100'],
            'defense_botch' => ['sometimes', 'boolean'],
            'defense_willpower' => ['sometimes', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        foreach (['attack_botch', 'attack_willpower', 'defense_botch', 'defense_willpower'] as $flag) {
            if (! $this->has($flag)) {
                $this->merge([$flag => false]);
            }
        }
    }
}
