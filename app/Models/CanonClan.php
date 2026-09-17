<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class CanonClan extends Model
{
    protected $fillable = [
        'slug',
        'name',
        'nickname',
        'description',
        'weakness',
        'weakness_system',
        'parent_clan_id',
        'is_bloodline',
        'is_playable',
    ];

    protected function casts(): array
    {
        return [
            'is_bloodline' => 'boolean',
            'is_playable' => 'boolean',
        ];
    }

    /**
     * @return BelongsToMany<CanonSect, $this>
     */
    public function sects(): BelongsToMany
    {
        return $this->belongsToMany(
            CanonSect::class,
            'canon_clan_sects',
            'clan_id',
            'sect_id',
        )
            ->withPivot(['since_year', 'until_year', 'note'])
            ->withTimestamps();
    }
}
