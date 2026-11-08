<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property array{
 *     key: string,
 *     title: string,
 *     description: string,
 *     variables: list<string>,
 *     group: string,
 *     content: string,
 *     is_overridden: bool,
 *     is_json_required: bool
 * } $resource
 */
class WorkshopPromptResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'key' => $this->resource['key'],
            'title' => $this->resource['title'],
            'description' => $this->resource['description'],
            'variables' => $this->resource['variables'],
            'group' => $this->resource['group'],
            'content' => $this->resource['content'],
            'is_overridden' => $this->resource['is_overridden'],
            'is_json_required' => $this->resource['is_json_required'],
        ];
    }
}
