<?php

namespace App\Http\Resources;

use App\Models\ChronicleMember;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ChronicleMember
 */
class ChronicleMembershipResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'chronicle_id' => (int) $this->chronicle_id,
            'chronicle_title' => $this->chronicle->title,
            'role' => $this->role->value,
            'joined_at' => $this->joined_at->toIso8601String(),
        ];
    }
}
