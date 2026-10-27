<?php

namespace App\Http\Requests;

use App\Enums\CharacterHealthDamage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCharacterStateRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'blood_pool' => ['required', 'integer', 'min:0', 'max:50'],
            'temporary_willpower' => ['required', 'integer', 'min:0', 'max:10'],
            'health_level' => ['required', 'integer', 'min:0', 'max:7'],
            'health_damage' => ['required', Rule::enum(CharacterHealthDamage::class)],
        ];
    }
}
