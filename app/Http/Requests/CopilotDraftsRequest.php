<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CopilotDraftsRequest extends FormRequest
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
            'npc_name' => ['required', 'string', 'max:64'],
            'prompt' => ['required', 'string', 'max:2000'],
        ];
    }
}
