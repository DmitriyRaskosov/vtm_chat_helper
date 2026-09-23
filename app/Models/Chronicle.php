<?php

namespace App\Models;

use App\Enums\ChronicleStatus;
use Database\Factories\ChronicleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'title',
    'description',
    'setting',
    'status',
    'created_by',
    'started_at',
    'archived_at',
])]
class Chronicle extends Model
{
    /** @use HasFactory<ChronicleFactory> */
    use HasFactory;

    public static function resolveId(?int $chronicleId): int
    {
        if ($chronicleId !== null) {
            return $chronicleId;
        }

        $id = static::query()->orderBy('id')->value('id');

        abort_if($id === null, 409, 'There is no available chronicle.');

        return (int) $id;
    }

    /**
     * @param  Builder<Chronicle>  $query
     * @return Builder<Chronicle>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', ChronicleStatus::Active);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return HasMany<WorldEntity, $this>
     */
    public function worldEntities(): HasMany
    {
        return $this->hasMany(WorldEntity::class);
    }

    /**
     * @return HasMany<GameSession, $this>
     */
    public function gameSessions(): HasMany
    {
        return $this->hasMany(GameSession::class);
    }

    protected function casts(): array
    {
        return [
            'status' => ChronicleStatus::class,
            'started_at' => 'datetime',
            'archived_at' => 'datetime',
        ];
    }
}
