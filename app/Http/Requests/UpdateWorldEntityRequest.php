<?php

namespace App\Http\Requests;

use App\Enums\WorldEntityType;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class UpdateWorldEntityRequest extends FormRequest
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
            'short_description' => ['sometimes', 'nullable', 'string', 'max:4000'],
            'aliases' => ['sometimes', 'array'],
            'aliases.*' => ['string', 'max:120'],
            'entity_type' => ['prohibited'],
            'chronicle_id' => ['sometimes', 'integer', 'exists:chronicles,id'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $entity = $this->route('worldEntity');
            $entityType = $entity?->entity_type instanceof WorldEntityType
                ? $entity->entity_type->value
                : (string) ($entity?->entity_type ?? '');
        });
    }
}
