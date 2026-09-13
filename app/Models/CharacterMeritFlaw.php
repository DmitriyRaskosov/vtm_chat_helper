<?php

namespace App\Models;

use App\Enums\CharacterMeritKind;
use Database\Factories\CharacterMeritFlawFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'character_id',
    'kind',
    'name',
    'cost',
    'note',
    'sort_order',
])]
class CharacterMeritFlaw extends Model
{
    /** @use HasFactory<CharacterMeritFlawFactory> */
    use HasFactory;

    protected $table = 'character_merits_flaws';

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
            'kind' => CharacterMeritKind::class,
            'cost' => 'integer',
            'sort_order' => 'integer',
        ];
    }
}
