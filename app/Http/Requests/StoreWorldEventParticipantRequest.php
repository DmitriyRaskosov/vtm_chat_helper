<?php

namespace App\Http\Requests;

use App\Enums\WorldEventParticipantRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreWorldEventParticipantRequest extends FormRequest
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
            'entity_id' => ['required', 'integer', 'exists:world_entities,id'],
            'role' => ['required', 'string', Rule::enum(WorldEventParticipantRole::class)],
        ];
    }
}
