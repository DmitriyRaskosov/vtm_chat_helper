<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LeaveSceneParticipantRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'system_message' => ['sometimes', 'nullable', 'string', 'max:500'],
        ];
    }
}
