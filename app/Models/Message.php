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
    'author_character_id',
    'copilot_request_id',
    'token_estimate',
    'token_estimator_version',
])]
class Message extends Model
{
    /** @use HasFactory<MessageFactory> */
    use HasFactory;

    public function displayAuthor(?string $characterCanonicalName = null): string
    {
        if ($this->author_character_id !== null) {
            $name = $characterCanonicalName ?? WorldEntity::query()
                ->whereKey($this->author_character_id)
                ->value('canonical_name');

            if (is_string($name) && $name !== '') {
                return $name;
            }
        }

        if ($this->npc_name !== null && $this->npc_name !== '') {
            return $this->npc_name;
        }

        return $this->user?->name ?? 'Аноним';
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
     * @return BelongsTo<Character, $this>
     */
    public function authorCharacter(): BelongsTo
    {
        return $this->belongsTo(Character::class, 'author_character_id');
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
