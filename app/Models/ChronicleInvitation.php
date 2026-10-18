<?php

namespace App\Models;

use App\Enums\ChronicleRole;
use Database\Factories\ChronicleInvitationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'chronicle_id',
    'token_hash',
    'role',
    'created_by',
    'expires_at',
    'max_uses',
    'uses',
    'revoked_at',
])]
class ChronicleInvitation extends Model
{
    /** @use HasFactory<ChronicleInvitationFactory> */
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
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    protected function casts(): array
    {
        return [
            'role' => ChronicleRole::class,
            'expires_at' => 'datetime',
            'max_uses' => 'integer',
            'uses' => 'integer',
            'revoked_at' => 'datetime',
        ];
    }
}
