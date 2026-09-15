<?php

namespace App\Models;

use App\Enums\ExtractionRunStatus;
use App\Enums\ExtractionSourceType;
use App\Enums\ExtractionTrigger;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'chronicle_id',
    'source_type',
    'source_id',
    'status',
    'trigger',
    'from_message_id',
    'to_message_id',
    'superseded_by_run_id',
    'driver',
    'model',
    'raw_response',
    'candidates',
    'user_id',
])]
class ExtractionRun extends Model
{
    /**
     * @return BelongsTo<Chronicle, $this>
     */
    public function chronicle(): BelongsTo
    {
        return $this->belongsTo(Chronicle::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<ExtractionRun, $this>
     */
    public function supersededBy(): BelongsTo
    {
        return $this->belongsTo(self::class, 'superseded_by_run_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'chronicle_id' => 'integer',
            'source_id' => 'integer',
            'source_type' => ExtractionSourceType::class,
            'status' => ExtractionRunStatus::class,
            'trigger' => ExtractionTrigger::class,
            'from_message_id' => 'integer',
            'to_message_id' => 'integer',
            'superseded_by_run_id' => 'integer',
            'raw_response' => 'array',
            'candidates' => 'array',
            'user_id' => 'integer',
        ];
    }
}
