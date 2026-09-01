<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        if ($this->filled('npc_name') && ! $this->user()?->isStoryteller()) {
            return false;
        }

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'max:4000'],
            'npc_name' => ['sometimes', 'nullable', 'string', 'max:64'],
            'scene_id' => ['sometimes', 'integer', 'exists:scenes,id'],
        ];
    }
}
