<?php

namespace Tests\Feature;

use App\Combat\CombatService;
use App\Combat\Exceptions\CharacterNotInSceneException;
use App\Combat\Exceptions\DuplicateCombatParticipantException;
use App\Combat\Exceptions\NoActiveCombatParticipantException;
use App\Enums\ChronicleRole;
use App\Enums\CombatEncounterStatus;
use App\Models\Character;
use App\Models\CombatEncounter;
use App\Models\CombatParticipant;
use App\Models\Scene;
use App\Models\SceneParticipant;
use App\Models\User;
use App\Scene\SceneLifecycleService;
use App\Scene\SceneParticipantService;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use Tests\Support\JoinsChronicle;
use Tests\TestCase;

class CombatServiceTest extends TestCase
{
    use JoinsChronicle;

    public function test_start_creates_active_encounter(): void
    {
        $scene = $this->scene();

        $encounter = app(CombatService::class)->startEncounter($scene);

        $this->assertSame($scene->id, $encounter->scene_id);
        $this->assertSame(CombatEncounterStatus::Active, $encounter->status);
        $this->assertSame(1, $encounter->current_round);
        $this->assertSame(0, $encounter->current_turn_index);
        $this->assertNotNull($encounter->started_at);
        $this->assertNull($encounter->ended_at);
        $this->assertSame(1, CombatEncounter::query()->where('scene_id', $scene->id)->count());
    }

    public function test_start_is_idempotent(): void
    {
        $scene = $this->scene();
        $service = app(CombatService::class);

        $first = $service->startEncounter($scene);
        $second = $service->startEncounter($scene);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, CombatEncounter::query()->where('scene_id', $scene->id)->count());
    }

    public function test_start_race_safe(): void
    {
        $race = $this->raceConnection();
        $sceneId = $this->insertCommittedScene($race);
        $winnerId = null;

        CombatEncounter::creating(function (CombatEncounter $encounter) use ($race, &$winnerId): void {
            if ($winnerId !== null) {
                return;
            }

            $winnerId = $race->table('combat_encounters')->insertGetId([
                'scene_id' => $encounter->scene_id,
                'status' => CombatEncounterStatus::Active->value,
                'current_round' => 1,
                'current_turn_index' => 0,
                'started_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        try {
            $scene = Scene::query()->findOrFail($sceneId);
            $encounter = app(CombatService::class)->startEncounter($scene);

            $this->assertSame($winnerId, $encounter->id);
            $this->assertSame(CombatEncounterStatus::Active, $encounter->status);
            $this->assertSame(1, CombatEncounter::query()->where('scene_id', $scene->id)->count());
        } finally {
            $this->deleteCommittedScene($race, $sceneId);
        }
    }

    public function test_add_sets_initiative_null(): void
    {
        $scene = $this->scene();
        $character = $this->characterIn($scene);
        $encounter = app(CombatService::class)->startEncounter($scene);

        $participant = app(CombatService::class)->addParticipant($encounter, $character);

        $this->assertNull($participant->initiative);
        $this->assertTrue($participant->is_active);
        $this->assertFalse($participant->is_hidden);
    }

    public function test_add_duplicate_throws(): void
    {
        $scene = $this->scene();
        $character = $this->characterIn($scene);
        $service = app(CombatService::class);
        $encounter = $service->startEncounter($scene);
        $service->addParticipant($encounter, $character);

        $this->expectException(DuplicateCombatParticipantException::class);
        $service->addParticipant($encounter, $character);
    }

    public function test_add_non_participant_throws(): void
    {
        $scene = $this->scene();
        $scene->loadMissing('gameSession');
        $outsider = Character::factory()->npc()->create([
            'chronicle_id' => $scene->gameSession->chronicle_id,
        ]);
        $encounter = app(CombatService::class)->startEncounter($scene);

        $this->expectException(CharacterNotInSceneException::class);
        app(CombatService::class)->addParticipant($encounter, $outsider);
    }

    public function test_initiative_sums_stats(): void
    {
        $scene = $this->scene();
        $character = $this->characterIn($scene, [
            'dexterity' => $this->dots(3),
            'wits' => $this->dots(2),
            'health' => ['level' => 0, 'damage' => 'bashing'],
        ]);
        $participant = $this->enroll($scene, $character);

        app(CombatService::class)->rollInitiative($participant->encounter);

        $this->assertSame(5, $participant->refresh()->initiative);
    }

    public function test_initiative_counts_temporary_dots(): void
    {
        $scene = $this->scene();
        $character = $this->characterIn($scene, [
            'dexterity' => $this->dots(3, temporary: true),
            'wits' => $this->dots(1),
            'health' => ['level' => 0, 'damage' => 'bashing'],
        ]);
        $participant = $this->enroll($scene, $character);

        app(CombatService::class)->rollInitiative($participant->encounter);

        $this->assertSame(4, $participant->refresh()->initiative);
    }

    public function test_initiative_adds_negative_health_penalty(): void
    {
        $scene = $this->scene();
        $character = $this->characterIn($scene, [
            'dexterity' => $this->dots(5),
            'wits' => $this->dots(3),
            'health' => ['level' => 4, 'damage' => 'lethal'],
        ]);
        $participant = $this->enroll($scene, $character);

        app(CombatService::class)->rollInitiative($participant->encounter);

        $this->assertSame(6, $participant->refresh()->initiative);
    }

    public function test_initiative_with_empty_stats(): void
    {
        $scene = $this->scene();
        $character = $this->characterIn($scene, null);
        $participant = $this->enroll($scene, $character);

        app(CombatService::class)->rollInitiative($participant->encounter);

        $this->assertSame(0, $participant->refresh()->initiative);
    }

    public function test_roll_resets_turn_and_round(): void
    {
        $scene = $this->scene();
        $character = $this->characterIn($scene, [
            'dexterity' => $this->dots(2),
            'wits' => $this->dots(2),
        ]);
        $participant = $this->enroll($scene, $character);
        $participant->encounter->update([
            'current_round' => 4,
            'current_turn_index' => 2,
        ]);

        $encounter = app(CombatService::class)->rollInitiative($participant->encounter);

        $this->assertSame(1, $encounter->current_round);
        $this->assertSame(0, $encounter->current_turn_index);
    }

    public function test_roll_recalculates_all_participants(): void
    {
        $scene = $this->scene();
        $first = $this->characterIn($scene, [
            'dexterity' => $this->dots(2),
            'wits' => $this->dots(1),
        ]);
        $second = $this->characterIn($scene, [
            'dexterity' => $this->dots(1),
            'wits' => $this->dots(1),
        ]);
        $service = app(CombatService::class);
        $encounter = $service->startEncounter($scene);
        $firstParticipant = $service->addParticipant($encounter, $first);
        $secondParticipant = $service->addParticipant($encounter, $second);
        $service->rollInitiative($encounter);
        $service->setInitiative($secondParticipant, 99);
        $first->update([
            'stats' => [
                'dexterity' => $this->dots(4),
                'wits' => $this->dots(1),
            ],
        ]);

        $service->rollInitiative($encounter->refresh());

        $this->assertSame(5, $firstParticipant->refresh()->initiative);
        $this->assertSame(2, $secondParticipant->refresh()->initiative);
    }

    public function test_sort_desc_by_initiative(): void
    {
        $scene = $this->scene();
        $low = $this->characterIn($scene, ['dexterity' => $this->dots(1), 'wits' => $this->dots(1)]);
        $high = $this->characterIn($scene, ['dexterity' => $this->dots(5), 'wits' => $this->dots(3)]);
        $mid = $this->characterIn($scene, ['dexterity' => $this->dots(3), 'wits' => $this->dots(2)]);
        $encounter = $this->enrollMany($scene, $low, $high, $mid);

        app(CombatService::class)->rollInitiative($encounter);

        $this->assertSame(
            [$high->id, $mid->id, $low->id],
            $this->orderedCharacterIds($encounter->refresh()),
        );
        $this->assertSortDoesNotQuery($encounter->refresh());
    }

    public function test_sort_ties_by_first_stat(): void
    {
        $scene = $this->scene();
        $slower = $this->characterIn($scene, ['dexterity' => $this->dots(2), 'wits' => $this->dots(3)]);
        $faster = $this->characterIn($scene, ['dexterity' => $this->dots(4), 'wits' => $this->dots(1)]);
        $encounter = $this->enrollMany($scene, $slower, $faster);

        app(CombatService::class)->rollInitiative($encounter);

        $this->assertSame([$faster->id, $slower->id], $this->orderedCharacterIds($encounter->refresh()));
    }

    public function test_inactive_go_to_end(): void
    {
        $scene = $this->scene();
        $high = $this->characterIn($scene, ['dexterity' => $this->dots(5), 'wits' => $this->dots(2)]);
        $low = $this->characterIn($scene, ['dexterity' => $this->dots(1), 'wits' => $this->dots(1)]);
        $encounter = $this->enrollMany($scene, $high, $low);
        $service = app(CombatService::class);
        $service->rollInitiative($encounter);
        $leader = $encounter->participants()->where('character_id', $high->id)->firstOrFail();

        $service->setParticipantActive($leader, false);

        $this->assertSame([$low->id, $high->id], $this->orderedCharacterIds($encounter->refresh()));
    }

    public function test_no_sort_before_first_roll(): void
    {
        $scene = $this->scene();
        $first = $this->characterIn($scene);
        $second = $this->characterIn($scene);
        $third = $this->characterIn($scene);
        $service = app(CombatService::class);
        $encounter = $service->startEncounter($scene);
        $service->addParticipant($encounter, $first);
        $middle = $service->addParticipant($encounter, $second);
        $service->addParticipant($encounter, $third);
        $service->setParticipantActive($middle, false);

        $this->assertSame(
            [$first->id, $second->id, $third->id],
            $this->orderedCharacterIds($encounter->refresh()),
        );
    }

    public function test_next_turn_skips_inactive(): void
    {
        $scene = $this->scene();
        $high = $this->characterIn($scene, ['dexterity' => $this->dots(5), 'wits' => $this->dots(1)]);
        $mid = $this->characterIn($scene, ['dexterity' => $this->dots(3), 'wits' => $this->dots(1)]);
        $low = $this->characterIn($scene, ['dexterity' => $this->dots(1), 'wits' => $this->dots(1)]);
        $service = app(CombatService::class);
        $encounter = $this->enrollMany($scene, $high, $mid, $low);
        $service->rollInitiative($encounter);
        $middle = $encounter->participants()->where('character_id', $mid->id)->firstOrFail();
        $service->setParticipantActive($middle, false);

        $advanced = $service->nextTurn($encounter->refresh());
        $order = $this->orderedCharacterIds($advanced);

        $this->assertSame(1, $advanced->current_round);
        $this->assertSame($low->id, $order[$advanced->current_turn_index]);
    }

    public function test_next_turn_wraps_round_no_reroll(): void
    {
        $scene = $this->scene();
        $high = $this->characterIn($scene, ['dexterity' => $this->dots(4), 'wits' => $this->dots(1)]);
        $low = $this->characterIn($scene, ['dexterity' => $this->dots(1), 'wits' => $this->dots(1)]);
        $service = app(CombatService::class);
        $encounter = $this->enrollMany($scene, $high, $low);
        $service->rollInitiative($encounter);
        $before = $encounter->participants()->orderBy('id')->pluck('initiative', 'character_id')->all();

        $firstStep = $service->nextTurn($encounter->refresh());
        $this->assertSame(1, $firstStep->current_round);
        $this->assertSame($low->id, $this->orderedCharacterIds($firstStep)[$firstStep->current_turn_index]);

        $wrapped = $service->nextTurn($firstStep);
        $after = $wrapped->participants()->orderBy('id')->pluck('initiative', 'character_id')->all();

        $this->assertSame(2, $wrapped->current_round);
        $this->assertSame($high->id, $this->orderedCharacterIds($wrapped)[$wrapped->current_turn_index]);
        $this->assertSame($before, $after);
    }

    public function test_next_turn_errors_when_no_active(): void
    {
        $scene = $this->scene();
        $character = $this->characterIn($scene, ['dexterity' => $this->dots(2), 'wits' => $this->dots(1)]);
        $service = app(CombatService::class);
        $participant = $this->enroll($scene, $character);
        $service->rollInitiative($participant->encounter);
        $service->setParticipantActive($participant->refresh(), false);

        $this->expectException(NoActiveCombatParticipantException::class);
        $service->nextTurn($participant->encounter->refresh());
    }

    public function test_toggle_active_and_back(): void
    {
        $scene = $this->scene();
        $high = $this->characterIn($scene, ['dexterity' => $this->dots(5), 'wits' => $this->dots(1)]);
        $low = $this->characterIn($scene, ['dexterity' => $this->dots(1), 'wits' => $this->dots(1)]);
        $service = app(CombatService::class);
        $encounter = $this->enrollMany($scene, $high, $low);
        $service->rollInitiative($encounter);
        $leader = $encounter->participants()->where('character_id', $high->id)->firstOrFail();

        $inactive = $service->setParticipantActive($leader, false);
        $service->setParticipantActive($inactive, false);
        $encounter->refresh();

        $this->assertFalse($inactive->refresh()->is_active);
        $this->assertSame($high->id, $this->orderedCharacterIds($encounter)[$encounter->current_turn_index]);

        $active = $service->setParticipantActive($inactive, true);
        $encounter->refresh();

        $this->assertTrue($active->is_active);
        $this->assertSame([$high->id, $low->id], $this->orderedCharacterIds($encounter));
        $this->assertSame($high->id, $this->orderedCharacterIds($encounter)[$encounter->current_turn_index]);
    }

    public function test_reinserted_participant_follows_initiative(): void
    {
        $scene = $this->scene();
        $high = $this->characterIn($scene, ['dexterity' => $this->dots(5), 'wits' => $this->dots(1)]);
        $mid = $this->characterIn($scene, ['dexterity' => $this->dots(3), 'wits' => $this->dots(1)]);
        $low = $this->characterIn($scene, ['dexterity' => $this->dots(1), 'wits' => $this->dots(1)]);
        $service = app(CombatService::class);
        $encounter = $this->enrollMany($scene, $high, $mid, $low);
        $service->rollInitiative($encounter);
        $middle = $encounter->participants()->where('character_id', $mid->id)->firstOrFail();
        $encounter->update(['current_turn_index' => 1]);

        $service->setParticipantActive($middle, false);
        $encounter->refresh();
        $this->assertSame($mid->id, $this->orderedCharacterIds($encounter)[$encounter->current_turn_index]);

        $service->setParticipantActive($middle->refresh(), true);
        $encounter->refresh();

        $this->assertSame([$high->id, $mid->id, $low->id], $this->orderedCharacterIds($encounter));
        $this->assertSame($mid->id, $this->orderedCharacterIds($encounter)[$encounter->current_turn_index]);
    }

    public function test_set_initiative_resorts(): void
    {
        $scene = $this->scene();
        $high = $this->characterIn($scene, ['dexterity' => $this->dots(4), 'wits' => $this->dots(1)]);
        $low = $this->characterIn($scene, ['dexterity' => $this->dots(1), 'wits' => $this->dots(1)]);
        $service = app(CombatService::class);
        $encounter = $this->enrollMany($scene, $high, $low);
        $service->rollInitiative($encounter);
        $trailer = $encounter->participants()->where('character_id', $low->id)->firstOrFail();

        $service->setInitiative($trailer, 20);
        $encounter->refresh();

        $this->assertSame(20, $trailer->refresh()->initiative);
        $this->assertSame([$low->id, $high->id], $this->orderedCharacterIds($encounter));
        $this->assertSame($high->id, $this->orderedCharacterIds($encounter)[$encounter->current_turn_index]);
    }

    public function test_reveal_hidden(): void
    {
        $scene = $this->scene();
        $character = $this->characterIn($scene);
        $service = app(CombatService::class);
        $encounter = $service->startEncounter($scene);
        $hidden = $service->addParticipant($encounter, $character, true);
        $this->assertTrue($hidden->is_hidden);

        $revealed = $service->revealParticipant($hidden);
        $again = $service->revealParticipant($revealed);

        $this->assertFalse($revealed->is_hidden);
        $this->assertFalse($again->is_hidden);
    }

    public function test_finish_encounter(): void
    {
        $scene = $this->scene();
        $service = app(CombatService::class);
        $encounter = $service->startEncounter($scene);

        $finished = $service->finishEncounter($encounter);

        $this->assertSame(CombatEncounterStatus::Finished, $finished->status);
        $this->assertNotNull($finished->ended_at);
        $this->assertNull($service->startEncounter($scene)->ended_at);
        $this->assertNotSame($finished->id, $service->startEncounter($scene)->id);
    }

    public function test_scene_close_finishes_encounter(): void
    {
        $scene = $this->scene();
        $encounter = app(CombatService::class)->startEncounter($scene);

        app(SceneLifecycleService::class)->close($scene);

        $encounter->refresh();
        $this->assertSame(CombatEncounterStatus::Finished, $encounter->status);
        $this->assertNotNull($encounter->ended_at);
    }

    public function test_participant_left_scene_deactivates(): void
    {
        $scene = $this->scene();
        $character = $this->characterIn($scene);
        $storyteller = $this->storytellerFor($scene);
        $service = app(CombatService::class);
        $participant = $this->enroll($scene, $character);

        app(SceneParticipantService::class)->leave($scene, $character, $storyteller);

        $this->assertFalse($participant->refresh()->is_active);
        $this->assertNotNull(
            SceneParticipant::query()
                ->where('scene_id', $scene->id)
                ->where('character_id', $character->id)
                ->value('left_at'),
        );
    }

    private function scene(): Scene
    {
        return Scene::factory()->active()->create();
    }

    private function raceConnection(): Connection
    {
        config(['database.connections.race' => config('database.connections.pgsql')]);
        DB::purge('race');

        return DB::connection('race');
    }

    private function insertCommittedScene(Connection $race): int
    {
        $now = now();
        $chronicleId = $race->table('chronicles')->insertGetId([
            'title' => 'Race chronicle',
            'status' => 'active',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $sessionId = $race->table('game_sessions')->insertGetId([
            'chronicle_id' => $chronicleId,
            'title' => 'Race session',
            'status' => 'archived',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $race->table('scenes')->insertGetId([
            'game_session_id' => $sessionId,
            'position' => 1,
            'title' => 'Race scene',
            'status' => 'draft',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function deleteCommittedScene(Connection $race, int $sceneId): void
    {
        $sessionId = $race->table('scenes')->where('id', $sceneId)->value('game_session_id');
        $chronicleId = $sessionId === null
            ? null
            : $race->table('game_sessions')->where('id', $sessionId)->value('chronicle_id');

        $race->table('combat_encounters')->where('scene_id', $sceneId)->delete();
        $race->table('scenes')->where('id', $sceneId)->delete();
        if ($sessionId !== null) {
            $race->table('game_sessions')->where('id', $sessionId)->delete();
        }
        if ($chronicleId !== null) {
            $race->table('chronicles')->where('id', $chronicleId)->delete();
        }
    }

    private function characterIn(Scene $scene, ?array $stats = null): Character
    {
        $scene->loadMissing('gameSession');
        $character = Character::factory()->npc()->create([
            'chronicle_id' => $scene->gameSession->chronicle_id,
            'stats' => $stats,
        ]);
        SceneParticipant::factory()->create([
            'scene_id' => $scene->id,
            'character_id' => $character->id,
        ]);

        return $character;
    }

    private function enroll(Scene $scene, Character $character): CombatParticipant
    {
        $service = app(CombatService::class);
        $encounter = $service->startEncounter($scene);

        return $service->addParticipant($encounter, $character);
    }

    private function enrollMany(Scene $scene, Character ...$characters): CombatEncounter
    {
        $service = app(CombatService::class);
        $encounter = $service->startEncounter($scene);
        foreach ($characters as $character) {
            $service->addParticipant($encounter, $character);
        }

        return $encounter->refresh();
    }

    /**
     * @return list<int>
     */
    private function orderedCharacterIds(CombatEncounter $encounter): array
    {
        $participants = $encounter->participants()->with('character')->get();

        return app(CombatService::class)
            ->sortByInitiative($participants, $encounter->stat_keys ?? CombatService::DEFAULT_STAT_KEYS)
            ->map(fn (CombatParticipant $participant): int => (int) $participant->character_id)
            ->all();
    }

    private function assertSortDoesNotQuery(CombatEncounter $encounter): void
    {
        $participants = $encounter->participants()->with('character')->get();
        DB::flushQueryLog();
        DB::enableQueryLog();
        app(CombatService::class)->sortByInitiative(
            $participants,
            $encounter->stat_keys ?? CombatService::DEFAULT_STAT_KEYS,
        );
        $this->assertSame([], DB::getQueryLog());
        DB::disableQueryLog();
    }

    /**
     * @return list<int>
     */
    private function dots(int $filled, bool $temporary = false): array
    {
        $dots = array_fill(0, 5, 0);
        for ($i = 0; $i < $filled; $i++) {
            $dots[$i] = 1;
        }
        if ($temporary && $filled > 0) {
            $dots[$filled - 1] = 2;
        }

        return $dots;
    }

    private function storytellerFor(Scene $scene): User
    {
        $scene->loadMissing('gameSession.chronicle');
        $user = User::factory()->create();
        $this->joinChronicle($user, $scene->gameSession->chronicle, ChronicleRole::Storyteller);

        return $user;
    }
}
