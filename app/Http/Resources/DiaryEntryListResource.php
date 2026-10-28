<?php

namespace App\Http\Resources;

use App\Enums\DiaryEmotion;
use App\Models\CharacterDiaryEntry;
use App\Models\Scene;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Элемент списка дневника (GET /characters/{id}/diary).
 *
 * @mixin CharacterDiaryEntry
 */
class DiaryEntryListResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $entry = $this->resource;
        $text = trim((string) $entry->entry);
        $emotion = $entry->getAttribute('emotion');

        return [
            'id' => (int) $entry->id,
            'character_id' => (int) $entry->character_id,
            'scene_id' => $entry->scene_id === null ? null : (int) $entry->scene_id,
            'scene_title' => $entry->scene instanceof Scene ? $entry->scene->title : null,
            'level' => (int) $entry->level,
            'from_message_id' => $entry->from_message_id === null ? null : (int) $entry->from_message_id,
            'to_message_id' => $entry->to_message_id === null ? null : (int) $entry->to_message_id,
            'is_stale' => (bool) $entry->is_stale,
            'importance' => $entry->importance === null ? null : (float) $entry->importance,
            'emotion' => $emotion instanceof DiaryEmotion ? $emotion->value : null,
            'preview' => mb_substr($text, 0, 300),
            'word_count' => count(preg_split('/\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: []),
            'created_at' => $entry->created_at?->toIso8601String(),
        ];
    }
}
