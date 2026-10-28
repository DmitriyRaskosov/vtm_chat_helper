<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SetGameSessionTurnRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'character_id' => ['nullable', 'integer', 'exists:characters,id'],
        ];
    }
}
