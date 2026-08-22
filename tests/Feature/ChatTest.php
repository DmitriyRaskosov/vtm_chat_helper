<?php

namespace Tests\Feature;

use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChatTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_post_a_message_and_it_persists(): void
    {
        $user = User::factory()->create(['name' => 'Анна']);

        $this->actingAs($user)
            ->postJson('/chat/messages', ['body' => 'Каинит входит в Элизиум.'])
            ->assertCreated()
            ->assertJsonPath('message.body', 'Каинит входит в Элизиум.')
            ->assertJsonPath('message.author', 'Анна');

        $this->assertDatabaseHas('messages', [
            'user_id' => $user->id,
            'body' => 'Каинит входит в Элизиум.',
        ]);

        $this->actingAs($user)
            ->get('/chat')
            ->assertOk()
            ->assertSee('Каинит входит в Элизиум.')
            ->assertSee('Анна')
            ->assertDontSee('Панель рассказчика');
    }

    public function test_messages_from_another_user_are_visible(): void
    {
        $anna = User::factory()->create(['name' => 'Анна']);
        $boris = User::factory()->create(['name' => 'Борис']);

        Message::factory()->create([
            'user_id' => $anna->id,
            'body' => 'Добрый вечер.',
        ]);

        $this->actingAs($boris)
            ->get('/chat')
            ->assertOk()
            ->assertSee('Добрый вечер.')
            ->assertSee('Анна');
    }

    public function test_storyteller_sees_the_empty_panel(): void
    {
        $st = User::factory()->storyteller()->create(['name' => 'СТ']);

        $this->actingAs($st)
            ->get('/chat')
            ->assertOk()
            ->assertSee('Панель рассказчика');
    }
}
