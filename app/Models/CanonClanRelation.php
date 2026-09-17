<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CanonClanRelation extends Model
{
    protected $fillable = [
        'from_clan_id',
        'to_clan_id',
        'relation_type',
        'intensity',
        'since_year',
        'until_year',
        'is_symmetric',
        'note',
        'source',
    ];

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

    /**
     * Все отношения клана — и исходящие, и входящие симметричные.
     *
     * @return Builder<static>
     */
    public static function relationsOf(int $clanId): Builder
    {
        return static::query()
            ->where(function ($q) use ($clanId) {
                $q->where('from_clan_id', $clanId)
                    ->orWhere(function ($q2) use ($clanId) {
                        $q2->where('to_clan_id', $clanId)
                            ->where('is_symmetric', true);
                    });
            });
    }
}
