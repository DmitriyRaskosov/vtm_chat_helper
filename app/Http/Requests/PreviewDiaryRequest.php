<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PreviewDiaryRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'scene_id' => ['sometimes', 'integer', 'exists:scenes,id'],
            'prompt' => ['sometimes', 'nullable', 'string', 'max:6000'],
        ];
    }
}
