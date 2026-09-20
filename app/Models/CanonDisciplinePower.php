<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'discipline_id',
    'level',
    'name',
    'description',
    'system',
    'source_book',
])]
class CanonDisciplinePower extends Model
{
    protected function casts(): array
    {
        return [
            'level' => 'integer',
        ];
    }

    public function discipline(): BelongsTo
    {
        return $this->belongsTo(CanonDiscipline::class, 'discipline_id');
    }
}