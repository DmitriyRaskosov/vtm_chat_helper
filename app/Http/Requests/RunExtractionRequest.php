<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class RunExtractionRequest extends FormRequest
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
            'lore_entry_id' => ['nullable', 'integer', 'exists:lore_entries,id'],
            'character_id' => ['nullable', 'integer', 'exists:characters,id'],
            'scene_id' => ['nullable', 'integer', 'exists:scenes,id'],
            'from_message_id' => ['nullable', 'integer', 'min:1', 'required_with:to_message_id'],
            'to_message_id' => ['nullable', 'integer', 'min:1', 'required_with:from_message_id', 'gte:from_message_id'],
            'chronicle_id' => ['sometimes', 'integer', 'exists:chronicles,id'],
            'reparse' => ['sometimes', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $count = 0;
            if ($this->filled('lore_entry_id')) {
                $count++;
            }
            if ($this->filled('character_id')) {
                $count++;
            }
            if ($this->filled('scene_id')) {
                $count++;
            }

            if ($count !== 1) {
                $validator->errors()->add(
                    'lore_entry_id',
                    'Provide exactly one of lore_entry_id, character_id, or scene_id.',
                );
            }

            if ($this->boolean('reparse') && ! $this->filled('lore_entry_id')) {
                $validator->errors()->add(
                    'reparse',
                    'reparse is only supported for lore_entry_id.',
                );
            }
        });
    }
}
