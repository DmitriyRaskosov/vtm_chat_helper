<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Tests\TestCase;

class SmokeTest extends TestCase
{
    public function test_user_factory_works(): void
    {
        $user = User::factory()->create();

        $this->assertNotNull($user->id);
        $this->assertSame(UserRole::Player, $user->role);
        $this->assertDatabaseHas('users', ['id' => $user->id]);
    }

    public function test_storyteller_factory_state_works(): void
    {
        $user = User::factory()->storyteller()->create();

        $this->assertSame(UserRole::Storyteller, $user->role);
        $this->assertTrue($user->isStoryteller());
    }
}