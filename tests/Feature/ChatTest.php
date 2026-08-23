<?php

namespace Tests\Feature;

use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ChatTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_post_a_message_and_it_persists(): void
    {
        $user = User::factory()->create(['name' => 'Анна']);

        Sanctum::actingAs($user);

        $this->postJson('/api/messages', ['body' => 'Каинит входит в Элизиум.'])
            ->assertCreated()
            ->assertJsonPath('message.body', 'Каинит входит в Элизиум.')
            ->assertJsonPath('message.author', 'Анна');

        $this->assertDatabaseHas('messages', [
            'user_id' => $user->id,
            'body' => 'Каинит входит в Элизиум.',
        ]);

        $this->getJson('/api/messages')
            ->assertOk()
            ->assertJsonPath('messages.0.body', 'Каинит входит в Элизиум.');
    }

    public function test_messages_from_another_user_are_visible(): void
    {
        $anna = User::factory()->create(['name' => 'Анна']);
        $boris = User::factory()->create(['name' => 'Борис']);

        Message::factory()->create([
            'user_id' => $anna->id,
            'body' => 'Добрый вечер.',
        ]);

        Sanctum::actingAs($boris);

        $this->getJson('/api/messages')
            ->assertOk()
            ->assertJsonPath('messages.0.body', 'Добрый вечер.')
            ->assertJsonPath('messages.0.author', 'Анна')
            ->assertJsonPath('messages.0.mine', false);
    }

    public function test_storyteller_profile_flag_is_true(): void
    {
        $st = User::factory()->storyteller()->create(['name' => 'СТ']);

        Sanctum::actingAs($st);

        $this->getJson('/api/user')
            ->assertOk()
            ->assertJsonPath('user.is_storyteller', true);
    }
}
