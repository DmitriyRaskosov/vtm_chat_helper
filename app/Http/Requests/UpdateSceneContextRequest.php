<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSceneContextRequest extends FormRequest
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
            'expected_revision' => ['required', 'integer', 'min:0'],
            'location_entity_id' => ['sometimes', 'nullable', 'integer', 'exists:locations,id'],
            'atmosphere' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'situation' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'storyteller_notes' => ['sometimes', 'nullable', 'string', 'max:4000'],
        ];
    }
}
