<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ImportLibraryCharacterRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'library_character_id' => ['required', 'integer', 'exists:library_characters,id'],
            'canonical_name' => ['required', 'string', 'max:100'],
        ];
    }
}
