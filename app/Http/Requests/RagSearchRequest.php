<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RagSearchRequest extends FormRequest
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
            'q' => ['required', 'string', 'max:2000'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:20'],
            'chronicle_id' => ['required', 'integer', 'exists:chronicles,id'],
            'game_session_id' => ['sometimes', 'integer', 'exists:game_sessions,id'],
            'scene_id' => ['sometimes', 'integer', 'exists:scenes,id'],
        ];
    }
}
