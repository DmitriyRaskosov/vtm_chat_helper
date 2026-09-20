<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCharacterDisciplinesRequest extends FormRequest
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
            'disciplines' => ['required', 'array'],
            'disciplines.*.discipline_id' => ['required', 'integer', 'exists:canon_disciplines,id'],
            'disciplines.*.level' => ['required', 'integer', 'min:0', 'max:9'],
        ];
    }
}
