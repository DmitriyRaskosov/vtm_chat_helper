<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class UpdateCharacterBiographyRequest extends FormRequest
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
            'summary' => ['nullable', 'string', 'max:4000'],
            'full_text' => ['nullable', 'string', 'max:50000'],
            'principles' => ['nullable', 'string', 'max:4000'],
            'motivation' => ['nullable', 'string', 'max:4000'],
            'fears' => ['nullable', 'string', 'max:4000'],
            'desires' => ['nullable', 'string', 'max:4000'],
            'behavioral_rules' => ['nullable', 'string', 'max:4000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $summary = trim((string) $this->input('summary', ''));
            $fullText = trim((string) $this->input('full_text', ''));

            if ($summary === '' && $fullText === '') {
                $validator->errors()->add('summary', 'Biography requires a summary or full_text.');
            }
        });
    }
}
