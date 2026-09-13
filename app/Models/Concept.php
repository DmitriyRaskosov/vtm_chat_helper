<?php

namespace App\Models;

use App\Enums\ConceptType;
use App\Enums\WorldEntityType;
use Database\Factories\ConceptFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'id',
    'chronicle_id',
    'entity_type',
    'concept_type',
    'definition',
])]
class Concept extends Model
{
    /** @use HasFactory<ConceptFactory> */
    use HasFactory;

    public $incrementing = false;

    /**
     * @return BelongsTo<Chronicle, $this>
     */
    public function chronicle(): BelongsTo
    {
        return $this->belongsTo(Chronicle::class);
    }

    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'entity_type' => WorldEntityType::class,
            'concept_type' => ConceptType::class,
        ];
    }
}
