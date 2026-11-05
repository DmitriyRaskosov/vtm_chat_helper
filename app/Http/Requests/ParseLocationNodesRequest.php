<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ParseLocationNodesRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'environment_details' => ['required', 'string', 'max:4000'],
        ];
    }
}
