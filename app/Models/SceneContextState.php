<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['scene_id', 'last_summarized_message_id', 'last_l1_source_id'])]
class SceneContextState extends Model
{
    /**
     * @return BelongsTo<Scene, $this>
     */
    public function scene(): BelongsTo
    {
        return $this->belongsTo(Scene::class);
    }

    protected function casts(): array
    {
        return [
            'last_summarized_message_id' => 'integer',
            'last_l1_source_id' => 'integer',
        ];
    }
}
