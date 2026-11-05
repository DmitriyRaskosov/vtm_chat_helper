<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCharacterStatsRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'stats' => ['required', 'array'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function stats(): array
    {
        $stats = $this->validated('stats');

        return is_array($stats) ? $stats : [];
    }
}
