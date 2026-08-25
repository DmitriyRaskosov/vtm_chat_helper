<?php

namespace App\Http\Requests;

use App\Enums\RagSourceType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RagSearchRequest extends FormRequest
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
            'q' => ['required', 'string', 'max:2000'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:20'],
            'types' => ['sometimes', 'array'],
            'types.*' => ['string', Rule::enum(RagSourceType::class)],
        ];
    }
}
