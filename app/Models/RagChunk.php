<?php

namespace App\Models;

use App\Enums\RagSourceType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Pgvector\Laravel\HasNeighbors;
use Pgvector\Laravel\Vector;

#[Fillable(['source_type', 'source_id', 'chunk_index', 'title', 'content', 'metadata', 'embedding'])]
class RagChunk extends Model
{
    use HasNeighbors;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'source_type' => RagSourceType::class,
            'chunk_index' => 'integer',
            'metadata' => 'array',
            'embedding' => Vector::class,
        ];
    }
}
