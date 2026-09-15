<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreWorldEventSourceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->isStoryteller();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'message_id' => ['sometimes', 'nullable', 'integer', 'exists:messages,id'],
            'scene_id' => ['sometimes', 'nullable', 'integer', 'exists:scenes,id'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            if (! $this->filled('message_id') && ! $this->filled('scene_id')) {
                $validator->errors()->add('message_id', 'Provide message_id or scene_id.');
            }
        });
    }
}
