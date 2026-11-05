<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'chronicle_location_id',
    'parent_id',
    'name',
    'description',
])]
class LocationNode extends Model
{
    /**
     * @return BelongsTo<ChronicleLocation, $this>
     */
    public function chronicleLocation(): BelongsTo
    {
        return $this->belongsTo(ChronicleLocation::class);
    }

    /**
     * @return BelongsTo<LocationNode, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * @return HasMany<LocationNode, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    protected function casts(): array
    {
        return [
            'chronicle_location_id' => 'integer',
            'parent_id' => 'integer',
        ];
    }
}
