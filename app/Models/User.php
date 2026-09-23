<?php

namespace App\Models;

use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

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
     * One user owns at most one player character, globally. Ghouls belong
     * to that character via `domitor_character_id`, not to the user.
     *
     * @return HasOne<Character, $this>
     */
    public function character(): HasOne
    {
        return $this->hasOne(Character::class);
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
