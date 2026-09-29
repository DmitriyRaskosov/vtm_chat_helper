<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class UpdateCharacterTraitsRequest extends FormRequest
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
            'traits' => ['sometimes', 'array'],
            'traits.*.key' => ['nullable', 'string', 'max:64'],
            'traits.*.label' => ['required', 'string', 'max:120'],
            'traits.*.value' => ['required', 'string', 'max:2000'],
            'traits.*.sort_order' => ['sometimes', 'integer', 'min:0'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $traits = $this->input('traits', []);
            if (! is_array($traits)) {
                return;
            }

            $keys = [];
            foreach ($traits as $index => $row) {
                if (! is_array($row)) {
                    continue;
                }

                $key = trim((string) ($row['key'] ?? ''));
                if ($key === '') {
                    continue;
                }

                if (isset($keys[$key])) {
                    $validator->errors()->add("traits.{$index}.key", 'Trait keys must be unique.');

                    continue;
                }

                $keys[$key] = true;
            }
        });
    }
}
