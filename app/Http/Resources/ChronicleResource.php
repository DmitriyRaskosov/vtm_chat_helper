<?php

namespace App\Http\Resources;

use App\Models\ChronicleMember;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Хроника в контексте членства текущего пользователя.
 *
 * @mixin ChronicleMember
 */
class ChronicleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $membership = $this->resource;
        $chronicle = $membership->chronicle;

        return [
            'id' => (int) $chronicle->id,
            'title' => (string) $chronicle->title,
            'description' => $chronicle->description,
            'setting' => $chronicle->setting,
            'status' => $chronicle->status->value,
            'role' => $membership->role->value,
            'joined_at' => $membership->joined_at?->toIso8601String(),
        ];
    }
}
