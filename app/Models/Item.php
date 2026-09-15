<?php

namespace App\Models;

use App\Enums\ItemStatus;
use App\Enums\WorldEntityType;
use Database\Factories\ItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'id',
    'chronicle_id',
    'entity_type',
    'owner_entity_id',
    'status',
])]
class Item extends Model
{
    /** @use HasFactory<ItemFactory> */
    use HasFactory;

    public $incrementing = false;

    /**
     * @return BelongsTo<WorldEntity, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(WorldEntity::class, 'owner_entity_id');
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
            'id' => 'integer',
            'entity_type' => WorldEntityType::class,
            'status' => ItemStatus::class,
        ];
    }
}
