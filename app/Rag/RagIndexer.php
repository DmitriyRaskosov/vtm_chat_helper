<?php

namespace App\Rag;

use App\Enums\RagSourceType;
use App\Models\ContextSummary;
use App\Models\Message;
use App\Models\RagChunk;

class RagIndexer
{
    public function __construct(private EmbeddingProvider $embeddings) {}

    public function indexMessage(Message $message): RagChunk
    {
        $message->loadMissing('scene:id,game_session_id');

        $metadata = [
            'user_id' => $message->user_id,
            'scene_id' => $message->scene_id,
            'game_session_id' => $message->scene->game_session_id,
        ];

        if ($message->npc_name !== null && $message->npc_name !== '') {
            $metadata['npc_name'] = $message->npc_name;
        }

        return $this->upsert(
            RagSourceType::Message,
            (string) $message->id,
            0,
            $message->body,
            null,
            $metadata,
        );
    }

    public function indexLore(string $sourceId, string $title, string $content, int $chunkIndex = 0): RagChunk
    {
        return $this->upsert(
            RagSourceType::Lore,
            $sourceId,
            $chunkIndex,
            $content,
            $title,
            [],
        );
    }

    public function indexSummary(ContextSummary $summary): RagChunk
    {
        return $this->upsert(
            RagSourceType::Summary,
            (string) $summary->id,
            0,
            $summary->content,
            $summary->level->value,
            [
                'level' => $summary->level->value,
                'scene_id' => $summary->scene_id,
                'game_session_id' => $summary->game_session_id,
                'first_message_id' => $summary->first_message_id,
                'last_message_id' => $summary->last_message_id,
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function upsert(
        RagSourceType $type,
        string $sourceId,
        int $chunkIndex,
        string $content,
        ?string $title,
        array $metadata,
    ): RagChunk {
        return RagChunk::query()->updateOrCreate(
            [
                'source_type' => $type,
                'source_id' => $sourceId,
                'chunk_index' => $chunkIndex,
            ],
            [
                'title' => $title,
                'content' => $content,
                'metadata' => $metadata,
                'embedding' => $this->embeddings->embed($content),
            ],
        );
    }
}
