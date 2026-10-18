<?php

namespace App\Http\Requests;

use App\Enums\ChronicleRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreChronicleInvitationRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'role' => ['required', Rule::enum(ChronicleRole::class)],
            'expires_at' => ['required', 'date', 'after:now'],
            'max_uses' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
