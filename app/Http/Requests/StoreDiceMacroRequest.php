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
            'pool' => ['nullable', 'integer', 'min:0', 'max:100'],
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

        $this->merge([
            'pool' => $this->emptyPoolAsNull(),
            'stats' => $this->statList(),
        ]);
    }

    private function emptyPoolAsNull(): mixed
    {
        if (! $this->exists('pool')) {
            return null;
        }

        $pool = $this->input('pool');

        return $pool === '' || $pool === null ? null : $pool;
    }

    /**
     * @return list<string>
     */
    private function statList(): array
    {
        $stats = $this->input('stats');
        if (is_string($stats)) {
            $stats = explode(',', $stats);
        }
        if (! is_array($stats)) {
            return [];
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

        return $names;
    }
}
