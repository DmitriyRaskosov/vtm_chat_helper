<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCharacterStatusRequest extends FormRequest
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
            'revision' => ['required', 'integer', 'min:0'],
            'blood_pool' => ['sometimes', 'integer', 'min:0', 'max:50'],
            'temporary_willpower' => ['sometimes', 'integer', 'min:0', 'max:10'],
        ];
    }
}
