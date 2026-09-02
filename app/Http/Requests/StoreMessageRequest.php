<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            'copilot_request_id' => [
                'sometimes',
                'nullable',
                'integer',
                'exists:copilot_requests,id',
                'required_with:copilot_draft_index',
                Rule::prohibitedIf(fn (): bool => ! $this->filled('npc_name')),
            ],
            'copilot_draft_index' => [
                'sometimes',
                'nullable',
                'integer',
                'min:0',
                'required_with:copilot_request_id',
                Rule::prohibitedIf(fn (): bool => ! $this->filled('npc_name')),
            ],
        ];
    }
}
