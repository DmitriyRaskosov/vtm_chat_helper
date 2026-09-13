<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Pgvector\Laravel\HasNeighbors;
use Pgvector\Laravel\Vector;

#[Fillable([
    'message_id',
    'chronicle_id',
    'game_session_id',
    'scene_id',
    'content',
    'token_estimate',
    'embedding',
])]
class MessageEmbedding extends Model
{
    use HasNeighbors;

    /**
     * @return BelongsTo<Message, $this>
     */
    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }

    protected function casts(): array
    {
        return [
            'message_id' => 'integer',
            'chronicle_id' => 'integer',
            'game_session_id' => 'integer',
            'scene_id' => 'integer',
            'token_estimate' => 'integer',
            'embedding' => Vector::class,
        ];
    }
}
