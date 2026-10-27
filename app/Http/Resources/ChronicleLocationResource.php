<?php

namespace App\Http\Resources;

use App\Models\ChronicleLocation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ChronicleLocation
 */
class ChronicleLocationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (int) $this->id,
            'chronicle_id' => (int) $this->chronicle_id,
            'name' => $this->name,
            'description' => $this->description,
            'environment_details' => $this->environment_details,
            'image_path' => $this->image_path,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
