<?php

namespace App\Models;

use App\Enums\CharacterHealthDamage;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property CharacterHealthDamage $health_damage
 * @property int $health_penalty
 */
#[Fillable([
    'character_id',
    'blood_pool',
    'temporary_willpower',
    'health_level',
    'health_damage',
    'health_penalty',
])]
class CharacterState extends Model
{
    public $incrementing = false;

    protected $primaryKey = 'character_id';

    /**
     * @return BelongsTo<Character, $this>
     */
    public function character(): BelongsTo
    {
        return $this->belongsTo(Character::class);
    }

    protected function casts(): array
    {
        return [
            'character_id' => 'integer',
            'blood_pool' => 'integer',
            'temporary_willpower' => 'integer',
            'health_level' => 'integer',
            'health_damage' => CharacterHealthDamage::class,
            'health_penalty' => 'integer',
        ];
    }
}
