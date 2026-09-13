<?php

namespace App\Models;

use App\Enums\FactionStatus;
use App\Enums\FactionType;
use App\Enums\WorldEntityType;
use Database\Factories\FactionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'id',
    'chronicle_id',
    'entity_type',
    'parent_faction_id',
    'faction_type',
    'status',
])]
class Faction extends Model
{
    /** @use HasFactory<FactionFactory> */
    use HasFactory;

    public $incrementing = false;

    /**
     * @return BelongsTo<Faction, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_faction_id');
    }

    /**
     * @return HasMany<Faction, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_faction_id');
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
            'faction_type' => FactionType::class,
            'status' => FactionStatus::class,
        ];
    }
}
