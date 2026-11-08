<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePromptOverrideRequest extends FormRequest
{
    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'content' => ['required', 'string', 'min:1'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $content = $this->input('content');
        if (is_string($content)) {
            $this->merge(['content' => trim($content)]);
        }
    }
}
