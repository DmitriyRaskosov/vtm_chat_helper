<?php

namespace App\Rag;

use App\Models\Message;
use App\Models\MessageEmbedding;

class RagIndexer
{
    public function __construct(private MessageIndexer $messages) {}

    public function indexMessage(Message $message): MessageEmbedding
    {
        return $this->messages->index($message);
    }
}
