<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'combat_encounter_id',
    'character_id',
    'initiative',
    'is_active',
    'is_hidden',
])]
class CombatParticipant extends Model
{
    /**
     * @return BelongsTo<CombatEncounter, $this>
     */
    public function encounter(): BelongsTo
    {
        return $this->belongsTo(CombatEncounter::class, 'combat_encounter_id');
    }

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
            'combat_encounter_id' => 'integer',
            'character_id' => 'integer',
            'initiative' => 'integer',
            'is_active' => 'boolean',
            'is_hidden' => 'boolean',
        ];
    }
}
