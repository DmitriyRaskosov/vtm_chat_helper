<?php

namespace App\Http\Resources;

use App\Models\ChronicleInvitation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Приглашение без хеша и без открытого кода.
 *
 * @mixin ChronicleInvitation
 */
class ChronicleInvitationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (int) $this->id,
            'expires_at' => $this->expires_at->toIso8601String(),
            'max_uses' => $this->max_uses,
            'role' => $this->role->value,
        ];
    }
}
