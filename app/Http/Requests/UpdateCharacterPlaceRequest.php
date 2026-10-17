<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCharacterPlaceRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'sect_id' => ['sometimes', 'nullable', 'integer', 'exists:canon_sects,id'],
            'clan_id' => ['sometimes', 'nullable', 'integer', 'exists:canon_clans,id'],
            'lore_clearance_levels' => ['sometimes', 'array', 'min:1'],
            'lore_clearance_levels.*' => ['integer', 'min:0', 'max:5'],
        ];
    }
}
