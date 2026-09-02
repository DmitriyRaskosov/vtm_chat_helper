<?php

namespace App\Models;

use App\Context\TokenEstimator;
use Database\Factories\MessageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'scene_id',
    'body',
    'npc_name',
    'copilot_request_id',
    'token_estimate',
    'token_estimator_version',
])]
class Message extends Model
{
    /** @use HasFactory<MessageFactory> */
    use HasFactory;

    public function displayAuthor(): string
    {
        if ($this->npc_name !== null && $this->npc_name !== '') {
            return $this->npc_name;
        }

        return $this->user->name;
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Scene, $this>
     */
    public function scene(): BelongsTo
    {
        return $this->belongsTo(Scene::class);
    }

    /**
     * @return BelongsTo<CopilotRequest, $this>
     */
    public function copilotRequest(): BelongsTo
    {
        return $this->belongsTo(CopilotRequest::class);
    }

    protected static function booted(): void
    {
        static::saving(function (Message $message): void {
            if (! $message->isDirty('body') && $message->token_estimate !== null) {
                return;
            }

            $estimator = app(TokenEstimator::class);
            $message->token_estimate = $estimator->estimate((string) $message->body);
            $message->token_estimator_version = $estimator->version();
        });
    }
}
