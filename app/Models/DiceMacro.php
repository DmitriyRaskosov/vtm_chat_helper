<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'name',
    'pool',
    'difficulty',
    'dice_type',
    'use_willpower',
    'stats',
])]
class DiceMacro extends Model
{
    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'pool' => 'integer',
            'difficulty' => 'integer',
            'dice_type' => 'integer',
            'use_willpower' => 'boolean',
            'stats' => 'array',
        ];
    }
}
