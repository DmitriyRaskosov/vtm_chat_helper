<?php

namespace Tests\Feature;

use App\Enums\ChronicleRole;
use App\Enums\CombatEncounterStatus;
use App\Models\Character;
use App\Models\Chronicle;
use App\Models\CombatEncounter;
use App\Models\Scene;
use App\Models\SceneParticipant;
use App\Models\User;
use Tests\Support\JoinsChronicle;
use Tests\TestCase;

class CombatApiTest extends TestCase
{
    use JoinsChronicle;

    private User $storyteller;

    private User $player;

    private Chronicle $chronicle;

    private Scene $scene;

    protected function setUp(): void
    {
        parent::setUp();

        $this->scene = Scene::factory()->active()->create();
        $this->scene->load('gameSession.chronicle');
        $this->chronicle = $this->scene->gameSession->chronicle;
        $this->storyteller = User::factory()->create();
        $this->player = User::factory()->create();
        $this->joinChronicle($this->storyteller, $this->chronicle, ChronicleRole::Storyteller);
        $this->joinChronicle($this->player, $this->chronicle, ChronicleRole::Player);
    }

    public function test_start_returns_existing(): void
    {
        $first = $this->actingAs($this->storyteller, 'sanctum')
            ->postJson($this->combatUrl())
            ->assertCreated()
            ->assertJsonPath('combat.scene_id', $this->scene->id)
            ->assertJsonPath('combat.status', 'active')
            ->assertJsonPath('combat.current_round', 1)
            ->assertJsonPath('combat.current_turn_index', 0);

        $second = $this->actingAs($this->storyteller, 'sanctum')
            ->postJson($this->combatUrl())
            ->assertOk();

        $this->assertSame($first->json('combat.id'), $second->json('combat.id'));
        $this->assertSame(1, CombatEncounter::query()->where('scene_id', $this->scene->id)->count());
    }

    public function test_storyteller_sees_hidden(): void
    {
        $hidden = $this->characterIn('Тень', 'avatars/shadow.png');
        $combatId = $this->startCombat();
        $this->addParticipant($combatId, $hidden, true);

        $this->actingAs($this->storyteller, 'sanctum')
            ->getJson($this->combatUrl())
            ->assertOk()
            ->assertJsonPath('combat.participants.0.character_id', $hidden->id)
            ->assertJsonPath('combat.participants.0.name', 'Тень')
            ->assertJsonPath('combat.participants.0.avatar_path', 'avatars/shadow.png')
            ->assertJsonPath('combat.participants.0.is_hidden', true)
            ->assertJsonPath('combat.participants.0.position', 0);
    }

    public function test_player_sees_placeholder_for_hidden(): void
    {
        $hidden = $this->characterIn('Тень', 'avatars/shadow.png');
        $combatId = $this->startCombat();
        $this->addParticipant($combatId, $hidden, true);

        $response = $this->actingAs($this->player, 'sanctum')
            ->getJson($this->combatUrl())
            ->assertOk()
            ->assertJsonPath('combat.participants.0.position', 0)
            ->assertJsonPath('combat.participants.0.initiative', null)
            ->assertJsonPath('combat.participants.0.is_hidden', true);

        $response->assertJsonMissingPath('combat.participants.0.id');
        $response->assertJsonMissingPath('combat.participants.0.character_id');
        $response->assertJsonMissingPath('combat.participants.0.name');
        $response->assertJsonMissingPath('combat.participants.0.avatar_path');
    }

    public function test_hidden_keeps_position(): void
    {
        $first = $this->characterIn('Первый', null, ['dexterity' => $this->dots(5), 'wits' => $this->dots(1)]);
        $hidden = $this->characterIn('Тень', 'avatars/shadow.png', ['dexterity' => $this->dots(3), 'wits' => $this->dots(1)]);
        $third = $this->characterIn('Третий', null, ['dexterity' => $this->dots(1), 'wits' => $this->dots(1)]);
        $combatId = $this->startCombat();
        $this->addParticipant($combatId, $first);
        $this->addParticipant($combatId, $hidden, true);
        $this->addParticipant($combatId, $third);
        $this->actingAs($this->storyteller, 'sanctum')
            ->postJson("/api/combat/{$combatId}/roll-initiative")
            ->assertOk();

        $storyteller = $this->actingAs($this->storyteller, 'sanctum')->getJson($this->combatUrl())->assertOk();
        $player = $this->actingAs($this->player, 'sanctum')->getJson($this->combatUrl())->assertOk();

        $this->assertSame($hidden->id, $storyteller->json('combat.participants.1.character_id'));
        $this->assertSame(4, $storyteller->json('combat.participants.1.initiative'));
        $this->assertSame(1, $player->json('combat.participants.1.position'));
        $this->assertSame(4, $player->json('combat.participants.1.initiative'));
        $player->assertJsonMissingPath('combat.participants.1.character_id');
        $this->assertSame(
            $storyteller->json('combat.participants.0.character_id'),
            $player->json('combat.participants.0.character_id'),
        );
        $this->assertSame(
            $storyteller->json('combat.participants.2.character_id'),
            $player->json('combat.participants.2.character_id'),
        );
    }

    public function test_add_participant_success(): void
    {
        $character = $this->characterIn('Иван');
        $combatId = $this->startCombat();

        $this->actingAs($this->storyteller, 'sanctum')
            ->postJson("/api/combat/{$combatId}/participants", [
                'character_id' => $character->id,
            ])
            ->assertCreated()
            ->assertJsonPath('combat.participants.0.character_id', $character->id)
            ->assertJsonPath('combat.participants.0.initiative', null)
            ->assertJsonPath('combat.participants.0.is_active', true)
            ->assertJsonPath('combat.participants.0.is_hidden', false);
    }

    public function test_add_duplicate_returns_409(): void
    {
        $character = $this->characterIn('Иван');
        $combatId = $this->startCombat();
        $this->addParticipant($combatId, $character);

        $this->actingAs($this->storyteller, 'sanctum')
            ->postJson("/api/combat/{$combatId}/participants", [
                'character_id' => $character->id,
            ])
            ->assertStatus(409);
    }

    public function test_add_non_participant_returns_422(): void
    {
        $outsider = Character::factory()->npc()->create([
            'chronicle_id' => $this->chronicle->id,
            'canonical_name' => 'Зритель',
        ]);
        $combatId = $this->startCombat();

        $this->actingAs($this->storyteller, 'sanctum')
            ->postJson("/api/combat/{$combatId}/participants", [
                'character_id' => $outsider->id,
            ])
            ->assertStatus(422);
    }

    public function test_roll_with_default_stats(): void
    {
        $character = $this->characterIn('Иван', null, [
            'dexterity' => $this->dots(2),
            'wits' => $this->dots(2),
        ]);
        $combatId = $this->startCombat();
        $this->addParticipant($combatId, $character);

        $this->actingAs($this->storyteller, 'sanctum')
            ->postJson("/api/combat/{$combatId}/roll-initiative")
            ->assertOk()
            ->assertJsonPath('combat.stat_keys', ['dexterity', 'wits'])
            ->assertJsonPath('combat.participants.0.initiative', 4);
    }

    public function test_roll_with_custom_stats(): void
    {
        $character = $this->characterIn('Иван', null, [
            'strength' => $this->dots(3),
            'wits' => $this->dots(1),
        ]);
        $combatId = $this->startCombat();
        $this->addParticipant($combatId, $character);

        $this->actingAs($this->storyteller, 'sanctum')
            ->postJson("/api/combat/{$combatId}/roll-initiative", [
                'stat_keys' => ['strength', 'wits'],
            ])
            ->assertOk()
            ->assertJsonPath('combat.stat_keys', ['strength', 'wits'])
            ->assertJsonPath('combat.participants.0.initiative', 4);
    }

    public function test_roll_resets_turn_and_round(): void
    {
        $character = $this->characterIn('Иван', null, [
            'dexterity' => $this->dots(2),
            'wits' => $this->dots(1),
        ]);
        $combatId = $this->startCombat();
        $this->addParticipant($combatId, $character);
        CombatEncounter::query()->whereKey($combatId)->update([
            'current_round' => 4,
            'current_turn_index' => 2,
        ]);

        $this->actingAs($this->storyteller, 'sanctum')
            ->postJson("/api/combat/{$combatId}/roll-initiative")
            ->assertOk()
            ->assertJsonPath('combat.current_round', 1)
            ->assertJsonPath('combat.current_turn_index', 0);
    }

    public function test_roll_recalculates_all_participants(): void
    {
        $first = $this->characterIn('Первый', null, [
            'dexterity' => $this->dots(1),
            'wits' => $this->dots(1),
        ]);
        $second = $this->characterIn('Второй', null, [
            'dexterity' => $this->dots(2),
            'wits' => $this->dots(1),
        ]);
        $combatId = $this->startCombat();
        $this->addParticipant($combatId, $first);
        $secondParticipant = $this->addParticipant($combatId, $second);
        $this->actingAs($this->storyteller, 'sanctum')
            ->postJson("/api/combat/{$combatId}/roll-initiative")
            ->assertOk();
        $this->actingAs($this->storyteller, 'sanctum')
            ->postJson("/api/combat/{$combatId}/participants/{$secondParticipant}/initiative", [
                'initiative' => 99,
            ])
            ->assertOk();
        $first->update([
            'stats' => [
                'dexterity' => $this->dots(4),
                'wits' => $this->dots(1),
            ],
        ]);

        $response = $this->actingAs($this->storyteller, 'sanctum')
            ->postJson("/api/combat/{$combatId}/roll-initiative")
            ->assertOk()
            ->assertJsonPath('combat.current_round', 1)
            ->assertJsonPath('combat.current_turn_index', 0);

        $initiatives = collect($response->json('combat.participants'))->pluck('initiative', 'character_id');
        $this->assertSame(5, $initiatives[$first->id]);
        $this->assertSame(3, $initiatives[$second->id]);
    }

    public function test_reveal_hidden(): void
    {
        $hidden = $this->characterIn('Тень', 'avatars/shadow.png');
        $combatId = $this->startCombat();
        $participantId = $this->addParticipant($combatId, $hidden, true);

        $this->actingAs($this->storyteller, 'sanctum')
            ->postJson("/api/combat/{$combatId}/participants/{$participantId}/reveal")
            ->assertOk();

        $this->actingAs($this->player, 'sanctum')
            ->getJson($this->combatUrl())
            ->assertOk()
            ->assertJsonPath('combat.participants.0.character_id', $hidden->id)
            ->assertJsonPath('combat.participants.0.name', 'Тень')
            ->assertJsonPath('combat.participants.0.avatar_path', 'avatars/shadow.png')
            ->assertJsonPath('combat.participants.0.is_hidden', false);
    }

    public function test_finish_encounter(): void
    {
        $combatId = $this->startCombat();

        $this->actingAs($this->storyteller, 'sanctum')
            ->postJson("/api/combat/{$combatId}/finish")
            ->assertOk()
            ->assertJsonPath('combat.status', CombatEncounterStatus::Finished->value);

        $this->assertNull(
            CombatEncounter::query()->where('scene_id', $this->scene->id)->where('status', CombatEncounterStatus::Active)->first(),
        );
    }

    public function test_show_after_finish_returns_no_active(): void
    {
        $combatId = $this->startCombat();
        $this->actingAs($this->storyteller, 'sanctum')
            ->postJson("/api/combat/{$combatId}/finish")
            ->assertOk();

        $this->actingAs($this->storyteller, 'sanctum')
            ->getJson($this->combatUrl())
            ->assertOk()
            ->assertJsonPath('combat', null);

        $this->actingAs($this->player, 'sanctum')
            ->getJson($this->combatUrl())
            ->assertOk()
            ->assertJsonPath('combat', null);
    }

    public function test_storyteller_only_actions_reject_player(): void
    {
        $character = $this->characterIn('Иван');
        $combatId = $this->startCombat();
        $participantId = $this->addParticipant($combatId, $character);

        $this->actingAs($this->player, 'sanctum')
            ->postJson($this->combatUrl())
            ->assertForbidden();

        $this->actingAs($this->player, 'sanctum')
            ->postJson("/api/combat/{$combatId}/participants", ['character_id' => $character->id])
            ->assertForbidden();

        $this->actingAs($this->player, 'sanctum')
            ->patchJson("/api/combat/{$combatId}/participants/{$participantId}/active", ['is_active' => false])
            ->assertForbidden();

        $this->actingAs($this->player, 'sanctum')
            ->postJson("/api/combat/{$combatId}/participants/{$participantId}/reveal")
            ->assertForbidden();

        $this->actingAs($this->player, 'sanctum')
            ->postJson("/api/combat/{$combatId}/participants/{$participantId}/initiative", ['initiative' => 1])
            ->assertForbidden();

        $this->actingAs($this->player, 'sanctum')
            ->postJson("/api/combat/{$combatId}/roll-initiative")
            ->assertForbidden();

        $this->actingAs($this->player, 'sanctum')
            ->postJson("/api/combat/{$combatId}/next-turn")
            ->assertForbidden();

        $this->actingAs($this->player, 'sanctum')
            ->postJson("/api/combat/{$combatId}/finish")
            ->assertForbidden();
    }

    private function combatUrl(): string
    {
        return '/api/scenes/'.$this->scene->id.'/combat';
    }

    private function startCombat(): int
    {
        return (int) $this->actingAs($this->storyteller, 'sanctum')
            ->postJson($this->combatUrl())
            ->assertCreated()
            ->json('combat.id');
    }

    private function addParticipant(int $combatId, Character $character, bool $hidden = false): int
    {
        $response = $this->actingAs($this->storyteller, 'sanctum')
            ->postJson("/api/combat/{$combatId}/participants", [
                'character_id' => $character->id,
                'is_hidden' => $hidden,
            ])
            ->assertCreated();

        $participant = collect($response->json('combat.participants'))
            ->firstWhere('character_id', $character->id);

        return (int) $participant['id'];
    }

    private function characterIn(string $name, ?string $avatar = null, ?array $stats = null): Character
    {
        $character = Character::factory()->npc()->create([
            'chronicle_id' => $this->chronicle->id,
            'canonical_name' => $name,
            'avatar_path' => $avatar,
            'stats' => $stats,
        ]);
        SceneParticipant::factory()->create([
            'scene_id' => $this->scene->id,
            'character_id' => $character->id,
        ]);

        return $character;
    }

    /**
     * @return list<int>
     */
    private function dots(int $filled): array
    {
        $dots = array_fill(0, 5, 0);
        for ($i = 0; $i < $filled; $i++) {
            $dots[$i] = 1;
        }

        return $dots;
    }
}
