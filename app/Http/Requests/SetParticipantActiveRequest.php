<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SetParticipantActiveRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'is_active' => ['required', 'boolean'],
        ];
    }
}
