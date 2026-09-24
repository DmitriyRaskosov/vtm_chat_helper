<?php

namespace App\Http\Requests;

use App\Enums\WorldEntityType;
use App\World\WorldEntityService;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreWorldEntityRequest extends FormRequest
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
            'canonical_name' => ['required', 'string', 'max:120'],
            'entity_type' => ['required', Rule::in(array_map(
                fn (WorldEntityType $type): string => $type->value,
                WorldEntityService::DIRECTORY_TYPES,
            ))],
            'short_description' => ['sometimes', 'nullable', 'string', 'max:4000'],
            'aliases' => ['sometimes', 'array'],
            'aliases.*' => ['string', 'max:120'],
            'chronicle_id' => ['sometimes', 'integer', 'exists:chronicles,id'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }
        });
    }
}
