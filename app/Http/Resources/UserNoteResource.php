<?php

namespace App\Http\Resources;

use App\Models\UserNote;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin UserNote
 */
class UserNoteResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (int) $this->id,
            'character_id' => (int) $this->character_id,
            'chronicle_id' => (int) $this->chronicle_id,
            'body' => $this->body,
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
