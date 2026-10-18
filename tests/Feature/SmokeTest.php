<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

class SmokeTest extends TestCase
{
    public function test_user_factory_works(): void
    {
        $user = User::factory()->create();

        $this->assertNotNull($user->id);
        $this->assertDatabaseHas('users', ['id' => $user->id]);
    }
}
