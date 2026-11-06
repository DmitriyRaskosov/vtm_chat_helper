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
            'pool' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:100'],
            'difficulty' => ['sometimes', 'integer', 'min:1', 'max:20'],
            'dice_type' => ['sometimes', 'integer', Rule::in([4, 6, 8, 10, 12, 20])],
            'use_willpower' => ['sometimes', 'boolean'],
            'stats' => ['sometimes', 'array'],
            'stats.*' => ['string', 'max:64'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->exists('pool') && ($this->input('pool') === '' || $this->input('pool') === null)) {
            $this->merge(['pool' => null]);
        }

        if (! $this->exists('stats')) {
            return;
        }

        $stats = $this->input('stats');
        if (is_string($stats)) {
            $stats = explode(',', $stats);
        }
        if (! is_array($stats)) {
            $this->merge(['stats' => []]);

            return;
        }

        $names = [];
        foreach ($stats as $name) {
            if (! is_string($name)) {
                continue;
            }
            foreach (explode(',', $name) as $part) {
                $part = trim($part);
                if ($part !== '' && ! in_array($part, $names, true)) {
                    $names[] = $part;
                }
            }
        }

        $this->merge(['stats' => $names]);
    }
}
