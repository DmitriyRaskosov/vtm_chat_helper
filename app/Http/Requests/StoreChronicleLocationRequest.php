<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreChronicleLocationRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'description' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'environment_details' => ['sometimes', 'nullable', 'string', 'max:4000'],
            'scene_id' => ['sometimes', 'nullable', 'integer', 'exists:scenes,id'],
        ];
    }
}
