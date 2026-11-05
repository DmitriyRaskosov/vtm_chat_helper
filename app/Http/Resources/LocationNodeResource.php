<?php

namespace App\Http\Resources;

use App\Models\LocationNode;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin LocationNode
 */
class LocationNodeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (int) $this->id,
            'chronicle_location_id' => (int) $this->chronicle_location_id,
            'parent_id' => $this->parent_id === null ? null : (int) $this->parent_id,
            'name' => $this->name,
            'description' => $this->description,
        ];
    }
}
