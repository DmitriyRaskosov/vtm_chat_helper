<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'from_clan_id',
    'to_clan_id',
    'relation_type',
    'intensity',
    'since_year',
    'until_year',
    'is_symmetric',
    'note',
    'source',
])]
class CanonClanRelation extends Model
{
    protected function casts(): array
    {
        return [
            'intensity' => 'integer',
            'since_year' => 'integer',
            'until_year' => 'integer',
            'is_symmetric' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<CanonClan, $this>
     */
    public function fromClan(): BelongsTo
    {
        return $this->belongsTo(CanonClan::class, 'from_clan_id');
    }

    /**
     * @return BelongsTo<CanonClan, $this>
     */
    public function toClan(): BelongsTo
    {
        return $this->belongsTo(CanonClan::class, 'to_clan_id');
    }
}
