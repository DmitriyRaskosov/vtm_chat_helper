<?php

namespace App\Rag;

use App\Models\Message;
use App\Models\MessageEmbedding;

class MessageIndexer
{
    public function __construct(private EmbeddingProvider $embeddings) {}

    /**
     * @param  list<float>|null  $embedding
     */
    public function index(Message $message, ?array $embedding = null): MessageEmbedding
    {
        $message->loadMissing('scene.gameSession');

        $content = $message->body;

        return MessageEmbedding::query()->updateOrCreate(
            ['message_id' => $message->id],
            [
                'chronicle_id' => $message->scene->gameSession->chronicle_id,
                'game_session_id' => $message->scene->game_session_id,
                'scene_id' => $message->scene_id,
                'content' => $content,
                'token_estimate' => (int) ($message->token_estimate ?: 1),
                'embedding' => $embedding ?? $this->embeddings->embed($content),
            ],
        );
    }
}
