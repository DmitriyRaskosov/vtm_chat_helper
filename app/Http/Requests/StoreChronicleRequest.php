<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreChronicleRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string'],
            'setting' => ['nullable', 'string', 'max:120'],
            'status' => ['prohibited'],
            'created_by' => ['prohibited'],
            'chronicle_id' => ['prohibited'],
        ];
    }
}
