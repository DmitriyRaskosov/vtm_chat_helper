<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'chronicle_id',
    'name',
    'description',
    'environment_details',
    'image_path',
])]
class ChronicleLocation extends Model
{
    /**
     * @return BelongsTo<Chronicle, $this>
     */
    public function chronicle(): BelongsTo
    {
        return $this->belongsTo(Chronicle::class);
    }

    /**
     * @return HasMany<LocationNode, $this>
     */
    public function locationNodes(): HasMany
    {
        return $this->hasMany(LocationNode::class);
    }

    protected function casts(): array
    {
        return [
            'chronicle_id' => 'integer',
        ];
    }
}
