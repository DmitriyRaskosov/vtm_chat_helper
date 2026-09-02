<?php

namespace App\Models;

use App\Enums\ContextSummarySourceType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['context_summary_id', 'source_type', 'source_id', 'position'])]
class ContextSummarySource extends Model
{
    /**
     * @return BelongsTo<ContextSummary, $this>
     */
    public function summary(): BelongsTo
    {
        return $this->belongsTo(ContextSummary::class, 'context_summary_id');
    }

    protected function casts(): array
    {
        return [
            'source_type' => ContextSummarySourceType::class,
            'source_id' => 'integer',
            'position' => 'integer',
        ];
    }
}
