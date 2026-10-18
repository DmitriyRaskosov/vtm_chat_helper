<?php

namespace App\Models;

use App\Enums\ChronicleRole;
use Database\Factories\ChronicleMemberFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property ChronicleRole $role
 * @property Carbon $joined_at
 * @property Carbon|null $left_at
 */
#[Fillable([
    'chronicle_id',
    'user_id',
    'role',
    'joined_at',
    'left_at',
])]
class ChronicleMember extends Model
{
    /** @use HasFactory<ChronicleMemberFactory> */
    use HasFactory;

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

    protected function casts(): array
    {
        return [
            'role' => ChronicleRole::class,
            'joined_at' => 'datetime',
            'left_at' => 'datetime',
        ];
    }
}
