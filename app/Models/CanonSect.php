<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class CanonSect extends Model
{
    protected $fillable = [
        'slug',
        'name',
        'description',
        'founded_year',
        'ended_year',
        'is_independent',
    ];

    protected function casts(): array
    {
        return [
            'is_independent' => 'boolean',
        ];
    }

    /**
     * @return BelongsToMany<CanonClan, $this>
     */
    public function clans(): BelongsToMany
    {
        return $this->belongsToMany(
            CanonClan::class,
            'canon_clan_sects',
            'sect_id',
            'clan_id',
        )
            ->withPivot(['since_year', 'until_year', 'note'])
            ->withTimestamps();
    }
}
