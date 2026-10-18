<?php

namespace App\Models;

use App\Enums\ChronicleRole;
use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

/**
 * @property UserRole $role
 */
#[Fillable(['name', 'login', 'email', 'password', 'role'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * @return HasMany<Message, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    /**
     * @return HasMany<Character, $this>
     */
    public function characters(): HasMany
    {
        return $this->hasMany(Character::class);
    }

    /**
     * @return HasMany<ChronicleMember, $this>
     */
    public function chronicleMemberships(): HasMany
    {
        return $this->hasMany(ChronicleMember::class);
    }

    /**
     * @return HasMany<Chronicle, $this>
     */
    public function createdChronicles(): HasMany
    {
        return $this->hasMany(Chronicle::class, 'created_by');
    }

    /**
     * @return HasMany<GameSession, $this>
     */
    public function createdGameSessions(): HasMany
    {
        return $this->hasMany(GameSession::class, 'created_by');
    }

    /**
     * @return HasMany<CopilotRequest, $this>
     */
    public function copilotRequests(): HasMany
    {
        return $this->hasMany(CopilotRequest::class, 'storyteller_id');
    }

    public function isStoryteller(): bool
    {
        return $this->role === UserRole::Storyteller;
    }

    public function isStorytellerIn(Chronicle $chronicle): bool
    {
        return ChronicleMember::query()
            ->where('chronicle_id', $chronicle->id)
            ->where('user_id', $this->id)
            ->whereNull('left_at')
            ->where('role', ChronicleRole::Storyteller)
            ->exists();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
        ];
    }
}
