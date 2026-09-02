<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'scene_id',
    'storyteller_id',
    'npc_name',
    'prompt',
    'drafts',
    'context_metadata',
    'model',
    'prompt_version',
    'builder_version',
    'selected_draft_index',
])]
class CopilotRequest extends Model
{
    /**
     * @return BelongsTo<Scene, $this>
     */
    public function scene(): BelongsTo
    {
        return $this->belongsTo(Scene::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function storyteller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'storyteller_id');
    }

    /**
     * @return HasOne<Message, $this>
     */
    public function message(): HasOne
    {
        return $this->hasOne(Message::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'drafts' => 'array',
            'context_metadata' => 'array',
            'selected_draft_index' => 'integer',
        ];
    }
}
