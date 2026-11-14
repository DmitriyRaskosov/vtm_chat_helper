<?php

namespace Tests\Feature;

use App\Enums\ChronicleRole;
use App\Events\CharacterEditedByStorytellerEvent;
use App\Models\Character;
use App\Models\Chronicle;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Tests\Support\JoinsChronicle;
use Tests\TestCase;

class CharacterApiTest extends TestCase
{
    use JoinsChronicle;

    private User $storyteller;

    private User $player;

    private User $foreign;

    private Chronicle $chronicle;

    private Character $character;

    protected function setUp(): void
    {
        parent::setUp();

        $this->storyteller = User::factory()->create();
        $this->player = User::factory()->create();
        $this->foreign = User::factory()->create();
        $this->chronicle = Chronicle::factory()->create([
            'title' => 'Тестовая хроника',
            'created_by' => $this->storyteller->id,
        ]);
        $this->joinChronicle($this->storyteller, $this->chronicle, ChronicleRole::Storyteller);
        $this->joinChronicle($this->player, $this->chronicle, ChronicleRole::Player);
        $this->joinChronicle($this->foreign, $this->chronicle, ChronicleRole::Player);
        $this->character = Character::factory()->player($this->player)->forChronicle($this->chronicle)->create([
            'canonical_name' => 'Аделина',
        ]);
    }

    public function test_storyteller_edit_sets_flag(): void
    {
        Event::fake([CharacterEditedByStorytellerEvent::class]);

        $this->assertNull($this->character->fresh()->updated_by_storyteller_at);

        $this->actingAs($this->player, 'sanctum')
            ->patchJson($this->statsPath(), $this->statsPayload())
            ->assertOk();

        $this->assertNull($this->character->fresh()->updated_by_storyteller_at);
        Event::assertNotDispatched(CharacterEditedByStorytellerEvent::class);

        $this->actingAs($this->storyteller, 'sanctum')
            ->patchJson($this->statsPath(), $this->statsPayload())
            ->assertOk();

        $this->assertNotNull($this->character->fresh()->updated_by_storyteller_at);
        Event::assertDispatched(
            CharacterEditedByStorytellerEvent::class,
            function (CharacterEditedByStorytellerEvent $event): bool {
                $channels = $event->broadcastOn();

                return $event->characterId === $this->character->id
                    && $event->userId === $this->player->id
                    && $event->broadcastWith() === [
                        'character_id' => $this->character->id,
                        'user_id' => $this->player->id,
                    ]
                    && $channels[0]->name === 'private-chronicle.'.$this->chronicle->id;
            },
        );

        $profile = $this->actingAs($this->player, 'sanctum')
            ->getJson('/api/profile/characters')
            ->assertOk()
            ->assertJsonPath('data.0.id', $this->character->id);

        $this->assertIsString($profile->json('data.0.updated_by_storyteller_at'));
        $this->assertNotSame('', $profile->json('data.0.updated_by_storyteller_at'));
    }

    public function test_storyteller_identity_edit_sets_flag(): void
    {
        $this->actingAs($this->storyteller, 'sanctum')
            ->patchJson('/api/characters/'.$this->character->id, [
                'concept' => 'Новая концепция',
            ])
            ->assertOk();

        $this->assertNotNull($this->character->fresh()->updated_by_storyteller_at);
    }

    public function test_player_edit_does_not_clear_storyteller_flag(): void
    {
        $this->actingAs($this->storyteller, 'sanctum')
            ->patchJson($this->statsPath(), $this->statsPayload())
            ->assertOk();

        $markedAt = $this->character->fresh()->updated_by_storyteller_at;
        $this->assertNotNull($markedAt);

        $this->actingAs($this->player, 'sanctum')
            ->patchJson($this->statsPath(), [
                'stats' => [
                    'strength' => [1, 1, 0, 0, 0],
                ],
            ])
            ->assertOk();

        $this->assertNotNull($this->character->fresh()->updated_by_storyteller_at);
    }

    public function test_acknowledge_update_clears_flag(): void
    {
        $this->patchJson($this->acknowledgePath())->assertUnauthorized();

        $this->actingAs($this->storyteller, 'sanctum')
            ->patchJson($this->statsPath(), $this->statsPayload())
            ->assertOk();
        $this->assertNotNull($this->character->fresh()->updated_by_storyteller_at);

        $this->actingAs($this->foreign, 'sanctum')
            ->patchJson($this->acknowledgePath())
            ->assertForbidden();
        $this->assertNotNull($this->character->fresh()->updated_by_storyteller_at);

        $this->actingAs($this->player, 'sanctum')
            ->patchJson($this->acknowledgePath())
            ->assertOk();

        $this->assertNull($this->character->fresh()->updated_by_storyteller_at);

        $this->actingAs($this->player, 'sanctum')
            ->patchJson($this->acknowledgePath())
            ->assertOk();
        $this->assertNull($this->character->fresh()->updated_by_storyteller_at);

        $this->actingAs($this->player, 'sanctum')
            ->getJson('/api/profile/characters')
            ->assertOk()
            ->assertJsonPath('data.0.updated_by_storyteller_at', null);
    }

    private function statsPath(): string
    {
        return '/api/characters/'.$this->character->id.'/stats';
    }

    private function acknowledgePath(): string
    {
        return '/api/characters/'.$this->character->id.'/acknowledge-update';
    }

    /**
     * @return array<string, mixed>
     */
    private function statsPayload(): array
    {
        return [
            'stats' => [
                'strength' => [1, 0, 0, 0, 0],
            ],
        ];
    }
}
