<?php

namespace App\Models;

use App\Enums\WorldEntityAliasType;
use Database\Factories\WorldEntityAliasFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'entity_id',
    'chronicle_id',
    'alias',
    'normalized_alias',
    'alias_type',
    'language',
])]
class WorldEntityAlias extends Model
{
    /** @use HasFactory<WorldEntityAliasFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<WorldEntity, $this>
     */
    public function entity(): BelongsTo
    {
        return $this->belongsTo(WorldEntity::class, 'entity_id');
    }

    /**
     * @return BelongsTo<Chronicle, $this>
     */
    public function chronicle(): BelongsTo
    {
        return $this->belongsTo(Chronicle::class);
    }

    protected function casts(): array
    {
        return [
            'alias_type' => WorldEntityAliasType::class,
        ];
    }
}
