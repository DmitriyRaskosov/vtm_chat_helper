<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateUserNoteRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'scene_id' => ['sometimes', 'nullable', 'integer', 'exists:scenes,id'],
            'content' => ['required', 'string', 'max:20000'],
        ];
    }
}
