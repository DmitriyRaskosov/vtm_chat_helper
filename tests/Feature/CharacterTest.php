<?php

namespace Tests\Feature;

use App\Enums\CharacterType;
use App\Enums\FactionType;
use App\Enums\WorldEntityType;
use App\Models\Chronicle;
use App\Models\CopilotRequest;
use App\Models\Message;
use App\Models\MessageEmbedding;
use App\Models\Scene;
use App\Models\User;
use App\Models\WorldEntity;
use App\World\MixedChronicleException;
use App\World\WorldEntityService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CharacterTest extends TestCase
{
    use RefreshDatabase;

    public function test_character_users_table_is_not_created(): void
    {
        $this->assertFalse(Schema::hasTable('character_users'));
    }

    public function test_service_creates_player_and_npc_characters(): void
    {
        $chronicle = Chronicle::factory()->create();
        $service = $this->app->make(WorldEntityService::class);
        $playerUser = User::factory()->create();
        $clan = $service->create(
            $chronicle,
            WorldEntityType::Faction,
            'Вентру',
            typed: ['faction_type' => FactionType::Clan],
        );
        $sire = $service->create($chronicle, WorldEntityType::Character, 'Сир');

        $npc = $service->create(
            $chronicle,
            WorldEntityType::Character,
            'Виктория',
            typed: [
                'character_type' => CharacterType::Npc,
                'clan_entity_id' => $clan->id,
                'sire_character_id' => $sire->id,
                'generation' => 8,
                'nature' => 'Architect',
                'demeanor' => 'Director',
                'concept' => 'Князь Праги',
            ],
        );
        $player = $service->create(
            $chronicle,
            WorldEntityType::Character,
            'Анна',
            typed: [
                'character_type' => CharacterType::Player,
                'user_id' => $playerUser->id,
            ],
        );

        $this->assertSame(CharacterType::Npc, $npc->character->character_type);
        $this->assertNull($npc->character->user_id);
        $this->assertSame($clan->id, $npc->character->clan_entity_id);
        $this->assertSame($sire->id, $npc->character->sire_character_id);
        $this->assertSame(8, $npc->character->generation);
        $this->assertSame('Князь Праги', $npc->character->concept);

        $this->assertSame(CharacterType::Player, $player->character->character_type);
        $this->assertSame($playerUser->id, $player->character->user_id);
        $this->assertTrue($playerUser->character->is($player->character));
    }

    public function test_player_character_user_id_is_globally_unique(): void
    {
        $user = User::factory()->create();
        $service = $this->app->make(WorldEntityService::class);
        $service->create(
            Chronicle::factory()->create(),
            WorldEntityType::Character,
            'Анна',
            typed: [
                'character_type' => CharacterType::Player,
                'user_id' => $user->id,
            ],
        );

        $this->expectException(QueryException::class);

        $service->create(
            Chronicle::factory()->create(),
            WorldEntityType::Character,
            'Другая Анна',
            typed: [
                'character_type' => CharacterType::Player,
                'user_id' => $user->id,
            ],
        );
    }

    public function test_player_character_requires_a_user(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->app->make(WorldEntityService::class)->create(
            Chronicle::factory()->create(),
            WorldEntityType::Character,
            'Анна',
            typed: ['character_type' => CharacterType::Player],
        );
    }

    public function test_npc_cannot_belong_to_a_user(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->app->make(WorldEntityService::class)->create(
            Chronicle::factory()->create(),
            WorldEntityType::Character,
            'Виктория',
            typed: [
                'character_type' => CharacterType::Npc,
                'user_id' => User::factory()->create()->id,
            ],
        );
    }

    public function test_service_creates_a_ghoul_bound_to_a_player_character(): void
    {
        $chronicle = Chronicle::factory()->create();
        $service = $this->app->make(WorldEntityService::class);
        $playerUser = User::factory()->create();
        $pc = $service->create(
            $chronicle,
            WorldEntityType::Character,
            'Анна',
            typed: [
                'character_type' => CharacterType::Player,
                'user_id' => $playerUser->id,
            ],
        );
        $ghoul = $service->create(
            $chronicle,
            WorldEntityType::Character,
            'Иван',
            typed: [
                'character_type' => CharacterType::Ghoul,
                'domitor_character_id' => $pc->id,
            ],
        );

        $this->assertSame(CharacterType::Ghoul, $ghoul->character->character_type);
        $this->assertNull($ghoul->character->user_id);
        $this->assertSame($pc->id, $ghoul->character->domitor_character_id);
        $this->assertTrue($ghoul->character->domitor->is($pc->character));
        $this->assertTrue($pc->character->ghouls->contains($ghoul->character));
        $this->assertTrue($ghoul->character->isPlayableBy($playerUser));
        $this->assertFalse($ghoul->character->isPlayableBy(User::factory()->create()));
    }

    public function test_ghoul_requires_a_domitor(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->app->make(WorldEntityService::class)->create(
            Chronicle::factory()->create(),
            WorldEntityType::Character,
            'Иван',
            typed: ['character_type' => CharacterType::Ghoul],
        );
    }

    public function test_ghoul_cannot_belong_to_a_user(): void
    {
        $chronicle = Chronicle::factory()->create();
        $service = $this->app->make(WorldEntityService::class);
        $pc = $service->create(
            $chronicle,
            WorldEntityType::Character,
            'Анна',
            typed: [
                'character_type' => CharacterType::Player,
                'user_id' => User::factory()->create()->id,
            ],
        );

        $this->expectException(InvalidArgumentException::class);

        $service->create(
            $chronicle,
            WorldEntityType::Character,
            'Иван',
            typed: [
                'character_type' => CharacterType::Ghoul,
                'user_id' => User::factory()->create()->id,
                'domitor_character_id' => $pc->id,
            ],
        );
    }

    public function test_player_cannot_have_a_domitor(): void
    {
        $chronicle = Chronicle::factory()->create();
        $service = $this->app->make(WorldEntityService::class);
        $npc = $service->create($chronicle, WorldEntityType::Character, 'Виктория');

        $this->expectException(InvalidArgumentException::class);

        $service->create(
            $chronicle,
            WorldEntityType::Character,
            'Анна',
            typed: [
                'character_type' => CharacterType::Player,
                'user_id' => User::factory()->create()->id,
                'domitor_character_id' => $npc->id,
            ],
        );
    }

    public function test_ghoul_cannot_be_another_ghouls_domitor(): void
    {
        $chronicle = Chronicle::factory()->create();
        $service = $this->app->make(WorldEntityService::class);
        $pc = $service->create(
            $chronicle,
            WorldEntityType::Character,
            'Анна',
            typed: [
                'character_type' => CharacterType::Player,
                'user_id' => User::factory()->create()->id,
            ],
        );
        $ghoul = $service->create(
            $chronicle,
            WorldEntityType::Character,
            'Иван',
            typed: [
                'character_type' => CharacterType::Ghoul,
                'domitor_character_id' => $pc->id,
            ],
        );

        $this->expectException(InvalidArgumentException::class);

        $service->create(
            $chronicle,
            WorldEntityType::Character,
            'Маша',
            typed: [
                'character_type' => CharacterType::Ghoul,
                'domitor_character_id' => $ghoul->id,
            ],
        );
    }

    public function test_ghoul_domitor_must_belong_to_the_same_chronicle(): void
    {
        $service = $this->app->make(WorldEntityService::class);
        $domitor = $service->create(
            Chronicle::factory()->create(),
            WorldEntityType::Character,
            'Анна',
            typed: [
                'character_type' => CharacterType::Player,
                'user_id' => User::factory()->create()->id,
            ],
        );

        $this->expectException(MixedChronicleException::class);

        $service->create(
            Chronicle::factory()->create(),
            WorldEntityType::Character,
            'Иван',
            typed: [
                'character_type' => CharacterType::Ghoul,
                'domitor_character_id' => $domitor->id,
            ],
        );
    }

    public function test_player_user_id_unique_still_allows_ghouls_of_that_character(): void
    {
        $user = User::factory()->create();
        $chronicle = Chronicle::factory()->create();
        $service = $this->app->make(WorldEntityService::class);
        $pc = $service->create(
            $chronicle,
            WorldEntityType::Character,
            'Анна',
            typed: [
                'character_type' => CharacterType::Player,
                'user_id' => $user->id,
            ],
        );

        $first = $service->create(
            $chronicle,
            WorldEntityType::Character,
            'Иван',
            typed: [
                'character_type' => CharacterType::Ghoul,
                'domitor_character_id' => $pc->id,
            ],
        );
        $second = $service->create(
            $chronicle,
            WorldEntityType::Character,
            'Маша',
            typed: [
                'character_type' => CharacterType::Ghoul,
                'domitor_character_id' => $pc->id,
            ],
        );

        $this->assertDatabaseCount('characters', 3);
        $this->assertTrue($pc->character->ghouls->contains($first->character));
        $this->assertTrue($pc->character->ghouls->contains($second->character));
    }

    public function test_clan_and_sire_must_belong_to_the_same_chronicle(): void
    {
        $service = $this->app->make(WorldEntityService::class);
        $clan = $service->create(
            Chronicle::factory()->create(),
            WorldEntityType::Faction,
            'Вентру',
            typed: ['faction_type' => FactionType::Clan],
        );

        $this->expectException(MixedChronicleException::class);

        $service->create(
            Chronicle::factory()->create(),
            WorldEntityType::Character,
            'Виктория',
            typed: ['clan_entity_id' => $clan->id],
        );
    }

    public function test_clan_must_be_a_faction(): void
    {
        $service = $this->app->make(WorldEntityService::class);
        $chronicle = Chronicle::factory()->create();
        $location = $service->create($chronicle, WorldEntityType::Location, 'Прага');

        $this->expectException(InvalidArgumentException::class);

        $service->create(
            $chronicle,
            WorldEntityType::Character,
            'Виктория',
            typed: ['clan_entity_id' => $location->id],
        );
    }

    public function test_clan_must_be_a_clan_faction(): void
    {
        $service = $this->app->make(WorldEntityService::class);
        $chronicle = Chronicle::factory()->create();
        $camarilla = $service->create(
            $chronicle,
            WorldEntityType::Faction,
            'Камарилья',
            typed: ['faction_type' => FactionType::Sect],
        );

        $this->expectException(InvalidArgumentException::class);

        $service->create(
            $chronicle,
            WorldEntityType::Character,
            'Виктория',
            typed: ['clan_entity_id' => $camarilla->id],
        );
    }

    public function test_renaming_a_character_keeps_message_and_rag_provenance(): void
    {
        $storyteller = User::factory()->storyteller()->create();
        $scene = Scene::query()->active()->with('gameSession')->firstOrFail();
        $npc = $this->app->make(WorldEntityService::class)->create(
            $scene->gameSession->chronicle,
            WorldEntityType::Character,
            'Виктория',
        );

        Sanctum::actingAs($storyteller);

        $legacy = Message::factory()->create([
            'user_id' => $storyteller->id,
            'scene_id' => $scene->id,
            'body' => 'Старая реплика без character_id.',
            'npc_name' => 'Виктория',
        ]);

        $posted = $this->postJson('/api/messages', [
            'body' => 'Добрый вечер, смертные.',
            'character_id' => $npc->id,
            'scene_id' => $scene->id,
        ])
            ->assertCreated()
            ->assertJsonPath('message.author', 'Виктория')
            ->assertJsonPath('message.mine', false)
            ->assertJsonPath('message.npc_name', 'Виктория')
            ->assertJsonPath('message.author_character_id', $npc->id)
            ->json('message.id');

        WorldEntity::query()->whereKey($npc->id)->update(['canonical_name' => 'Княгиня Виктория']);

        $this->getJson('/api/messages?scene_id='.$scene->id)
            ->assertOk()
            ->assertJsonPath('messages.0.author', 'Виктория')
            ->assertJsonPath('messages.0.author_character_id', null)
            ->assertJsonPath('messages.1.author', 'Княгиня Виктория')
            ->assertJsonPath('messages.1.author_character_id', $npc->id);

        $this->assertSame('Виктория', $legacy->fresh()->displayAuthor());
        $this->assertSame('Княгиня Виктория', Message::query()->findOrFail($posted)->displayAuthor());

        $embedding = MessageEmbedding::query()->where('message_id', $posted)->firstOrFail();
        $this->assertSame($posted, $embedding->message_id);
    }

    public function test_storyteller_can_generate_and_post_copilot_drafts_by_character_id(): void
    {
        $this->fakeOllamaDrafts(['Один.', 'Два.', 'Три.']);
        $storyteller = User::factory()->storyteller()->create();
        $scene = Scene::query()->active()->with('gameSession')->firstOrFail();
        $npc = $this->app->make(WorldEntityService::class)->create(
            $scene->gameSession->chronicle,
            WorldEntityType::Character,
            'Виктория',
        );

        Sanctum::actingAs($storyteller);

        $copilotRequestId = $this->postJson('/api/copilot/drafts', [
            'character_id' => $npc->id,
            'prompt' => 'Ответить с угрозой.',
            'scene_id' => $scene->id,
        ])->assertOk()->json('copilot_request_id');

        $copilotRequest = CopilotRequest::query()->findOrFail($copilotRequestId);
        $this->assertSame($npc->id, $copilotRequest->character_id);
        $this->assertSame('Виктория', $copilotRequest->npc_name);

        WorldEntity::query()->whereKey($npc->id)->update(['canonical_name' => 'Княгиня Виктория']);

        $this->postJson('/api/messages', [
            'body' => 'Отредактированный второй вариант.',
            'character_id' => $npc->id,
            'scene_id' => $scene->id,
            'copilot_request_id' => $copilotRequestId,
            'copilot_draft_index' => 1,
        ])
            ->assertCreated()
            ->assertJsonPath('message.author', 'Княгиня Виктория')
            ->assertJsonPath('message.author_character_id', $npc->id)
            ->assertJsonPath('message.npc_name', 'Княгиня Виктория');

        $this->assertDatabaseHas('copilot_requests', [
            'id' => $copilotRequestId,
            'npc_name' => 'Виктория',
            'selected_draft_index' => 1,
        ]);
    }

    public function test_player_can_post_as_their_character(): void
    {
        $player = User::factory()->create(['name' => 'Игрок']);
        $scene = Scene::query()->active()->with('gameSession')->firstOrFail();
        $pc = $this->app->make(WorldEntityService::class)->create(
            $scene->gameSession->chronicle,
            WorldEntityType::Character,
            'Анна',
            typed: [
                'character_type' => CharacterType::Player,
                'user_id' => $player->id,
            ],
        );

        Sanctum::actingAs($player);

        $this->postJson('/api/messages', [
            'body' => 'Каинит входит в Элизиум.',
            'character_id' => $pc->id,
            'scene_id' => $scene->id,
        ])
            ->assertCreated()
            ->assertJsonPath('message.author', 'Анна')
            ->assertJsonPath('message.mine', true)
            ->assertJsonPath('message.npc_name', null)
            ->assertJsonPath('message.author_character_id', $pc->id);
    }

    public function test_player_cannot_post_as_npc_character(): void
    {
        $scene = Scene::query()->active()->with('gameSession')->firstOrFail();
        $npc = $this->app->make(WorldEntityService::class)->create(
            $scene->gameSession->chronicle,
            WorldEntityType::Character,
            'Виктория',
        );

        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/messages', [
            'body' => 'Притворяюсь НПС.',
            'character_id' => $npc->id,
        ])->assertForbidden();
    }

    public function test_player_cannot_post_as_another_users_character(): void
    {
        $owner = User::factory()->create();
        $scene = Scene::query()->active()->with('gameSession')->firstOrFail();
        $pc = $this->app->make(WorldEntityService::class)->create(
            $scene->gameSession->chronicle,
            WorldEntityType::Character,
            'Анна',
            typed: [
                'character_type' => CharacterType::Player,
                'user_id' => $owner->id,
            ],
        );

        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/messages', [
            'body' => 'Это не мой персонаж.',
            'character_id' => $pc->id,
        ])->assertForbidden();
    }

    public function test_player_can_post_as_their_ghoul(): void
    {
        $player = User::factory()->create(['name' => 'Игрок']);
        $scene = Scene::query()->active()->with('gameSession')->firstOrFail();
        $service = $this->app->make(WorldEntityService::class);
        $pc = $service->create(
            $scene->gameSession->chronicle,
            WorldEntityType::Character,
            'Анна',
            typed: [
                'character_type' => CharacterType::Player,
                'user_id' => $player->id,
            ],
        );
        $ghoul = $service->create(
            $scene->gameSession->chronicle,
            WorldEntityType::Character,
            'Иван',
            typed: [
                'character_type' => CharacterType::Ghoul,
                'domitor_character_id' => $pc->id,
            ],
        );

        Sanctum::actingAs($player);

        $this->postJson('/api/messages', [
            'body' => 'Гуль кланяется.',
            'character_id' => $ghoul->id,
            'scene_id' => $scene->id,
        ])
            ->assertCreated()
            ->assertJsonPath('message.author', 'Иван')
            ->assertJsonPath('message.mine', true)
            ->assertJsonPath('message.npc_name', null)
            ->assertJsonPath('message.author_character_id', $ghoul->id);
    }

    public function test_player_cannot_post_as_another_users_ghoul(): void
    {
        $owner = User::factory()->create();
        $scene = Scene::query()->active()->with('gameSession')->firstOrFail();
        $service = $this->app->make(WorldEntityService::class);
        $pc = $service->create(
            $scene->gameSession->chronicle,
            WorldEntityType::Character,
            'Анна',
            typed: [
                'character_type' => CharacterType::Player,
                'user_id' => $owner->id,
            ],
        );
        $ghoul = $service->create(
            $scene->gameSession->chronicle,
            WorldEntityType::Character,
            'Иван',
            typed: [
                'character_type' => CharacterType::Ghoul,
                'domitor_character_id' => $pc->id,
            ],
        );

        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/messages', [
            'body' => 'Чужой гуль.',
            'character_id' => $ghoul->id,
        ])->assertForbidden();
    }

    public function test_storyteller_can_post_as_a_ghoul(): void
    {
        $storyteller = User::factory()->storyteller()->create();
        $scene = Scene::query()->active()->with('gameSession')->firstOrFail();
        $service = $this->app->make(WorldEntityService::class);
        $npc = $service->create(
            $scene->gameSession->chronicle,
            WorldEntityType::Character,
            'Виктория',
        );
        $ghoul = $service->create(
            $scene->gameSession->chronicle,
            WorldEntityType::Character,
            'Иван',
            typed: [
                'character_type' => CharacterType::Ghoul,
                'domitor_character_id' => $npc->id,
            ],
        );

        Sanctum::actingAs($storyteller);

        $this->postJson('/api/messages', [
            'body' => 'Слуга княгини говорит.',
            'character_id' => $ghoul->id,
            'scene_id' => $scene->id,
        ])
            ->assertCreated()
            ->assertJsonPath('message.author', 'Иван')
            ->assertJsonPath('message.mine', false)
            ->assertJsonPath('message.npc_name', 'Иван')
            ->assertJsonPath('message.author_character_id', $ghoul->id);
    }

    public function test_character_must_belong_to_the_scene_chronicle(): void
    {
        $storyteller = User::factory()->storyteller()->create();
        $foreign = $this->app->make(WorldEntityService::class)->create(
            Chronicle::factory()->create(),
            WorldEntityType::Character,
            'Чужак',
        );

        Sanctum::actingAs($storyteller);

        $this->postJson('/api/messages', [
            'body' => 'Из другой хроники.',
            'character_id' => $foreign->id,
        ])->assertConflict();
    }

    public function test_new_npc_identity_rejects_legacy_name_only_contract(): void
    {
        $storyteller = User::factory()->storyteller()->create();
        $scene = Scene::query()->active()->firstOrFail();

        Sanctum::actingAs($storyteller);

        $this->postJson('/api/copilot/drafts', [
            'npc_name' => 'Виктория',
            'prompt' => 'Тест.',
            'scene_id' => $scene->id,
        ])->assertUnprocessable()->assertJsonValidationErrors('character_id');

        $this->postJson('/api/messages', [
            'body' => 'Один.',
            'npc_name' => 'Виктория',
            'scene_id' => $scene->id,
        ])->assertUnprocessable()->assertJsonValidationErrors('npc_name');
    }

    /**
     * @param  list<string>  $drafts
     */
    private function fakeOllamaDrafts(array $drafts): void
    {
        $payload = json_encode(['drafts' => $drafts], JSON_UNESCAPED_UNICODE);

        Http::fake(function (Request $request) use ($payload) {
            return Http::response([
                'message' => ['content' => $payload],
            ]);
        });
    }
}
