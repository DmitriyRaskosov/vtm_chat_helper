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
            'sheet_data' => ['sometimes', 'array'],
            'sheet_data.backgrounds' => ['sometimes', 'array'],
            'sheet_data.backgrounds.*.name' => ['required', 'string', 'max:255'],
            'sheet_data.backgrounds.*.value' => ['required'],
            'sheet_data.merits_flaws' => ['sometimes', 'array'],
            'sheet_data.merits_flaws.*.name' => ['required', 'string', 'max:255'],
            'sheet_data.merits_flaws.*.points' => ['required', 'integer'],
            'sheet_data.merits_flaws.*.type' => ['required', 'string', 'in:merit,flaw'],
            'sheet_data.weakness' => ['sometimes', 'nullable', 'string'],
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function sheetData(): ?array
    {
        if (! $this->exists('sheet_data')) {
            return null;
        }

        $data = $this->validated('sheet_data');

        return is_array($data) ? $data : [];
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
