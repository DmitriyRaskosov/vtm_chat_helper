<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CanonLoreEntry extends Model
{
    protected $fillable = [
        'slug',
        'title',
        'text',
        'category',
        'tags',
        'source_book',
        'source_page',
        'source_url',
        'retrieved_at',
        'era_from',
        'era_to',
    ];

    protected function casts(): array
    {
        return [
            'tags' => 'array',
            'retrieved_at' => 'date',
            'source_page' => 'integer',
            'era_from' => 'integer',
            'era_to' => 'integer',
        ];
    }

    /**
     * @return HasMany<CanonLoreEntryEntity, $this>
     */
    public function entities(): HasMany
    {
        return $this->hasMany(CanonLoreEntryEntity::class, 'lore_entry_id');
    }
}
