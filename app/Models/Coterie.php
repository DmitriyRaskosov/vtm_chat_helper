<?php

namespace App\Models;

use App\Enums\FactionStatus;
use App\Enums\WorldEntityType;
use Database\Factories\CoterieFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'id',
    'chronicle_id',
    'entity_type',
    'sect_faction_id',
    'status',
])]
class Coterie extends Model
{
    /** @use HasFactory<CoterieFactory> */
    use HasFactory;

    public $incrementing = false;

    /**
     * @return BelongsTo<Faction, $this>
     */
    public function sectFaction(): BelongsTo
    {
        return $this->belongsTo(Faction::class, 'sect_faction_id');
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
            'status' => FactionStatus::class,
        ];
    }
}
