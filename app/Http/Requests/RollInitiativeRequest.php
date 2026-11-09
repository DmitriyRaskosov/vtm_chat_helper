<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RollInitiativeRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'stat_keys' => ['sometimes', 'array'],
            'stat_keys.*' => ['string', 'max:64'],
        ];
    }
}
