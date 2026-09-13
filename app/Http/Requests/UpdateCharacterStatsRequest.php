<?php

namespace App\Http\Requests;

use App\Enums\CharacterStatCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCharacterStatsRequest extends FormRequest
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
            'stats' => ['required', 'array'],
            'stats.*.category' => ['required', Rule::enum(CharacterStatCategory::class)],
            'stats.*.stat_key' => ['required', 'string', 'max:64'],
            'stats.*.display_name' => ['sometimes', 'string', 'max:120'],
            'stats.*.value' => ['required', 'integer', 'min:0', 'max:10'],
            'stats.*.maximum' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:10'],
            'stats.*.sort_order' => ['sometimes', 'integer', 'min:0'],
            'stats.*.specializations' => ['sometimes', 'array'],
        ];
    }
}
