<?php

namespace App\Models;

use App\Enums\CharacterHealthState;
use Database\Factories\CharacterStatusFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'character_id',
    'chronicle_id',
    'temporary_willpower',
    'blood_pool',
    'hunger',
    'health_state',
    'fatigue',
    'current_location_id',
    'revision',
])]
class CharacterStatus extends Model
{
    /** @use HasFactory<CharacterStatusFactory> */
    use HasFactory;

    protected $table = 'character_status';

    protected $primaryKey = 'character_id';

    public $incrementing = false;

    /**
     * @return BelongsTo<Character, $this>
     */
    public function character(): BelongsTo
    {
        return $this->belongsTo(Character::class);
    }

    /**
     * @return BelongsTo<Location, $this>
     */
    public function currentLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'current_location_id');
    }

    /**
     * @return HasMany<CharacterStatusEffect, $this>
     */
    public function effects(): HasMany
    {
        return $this->hasMany(CharacterStatusEffect::class, 'character_id', 'character_id');
    }

    /**
     * @return HasMany<CharacterStatusChange, $this>
     */
    public function changes(): HasMany
    {
        return $this->hasMany(CharacterStatusChange::class, 'character_id', 'character_id');
    }

    protected function casts(): array
    {
        return [
            'character_id' => 'integer',
            'chronicle_id' => 'integer',
            'temporary_willpower' => 'integer',
            'blood_pool' => 'integer',
            'hunger' => 'integer',
            'health_state' => CharacterHealthState::class,
            'fatigue' => 'integer',
            'current_location_id' => 'integer',
            'revision' => 'integer',
        ];
    }
}
