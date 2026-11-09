<?php

namespace Tests\Feature;

use App\Combat\CombatService;
use App\Enums\CharacterType;
use App\Enums\ChronicleRole;
use App\Enums\CombatEncounterStatus;
use App\Enums\SceneParticipantRole;
use App\Events\CombatParticipantUpdatedEvent;
use App\Events\CombatStartedEvent;
use App\Events\CombatTurnChangedEvent;
use App\Models\Character;
use App\Models\CombatEncounter;
use App\Models\CombatParticipant;
use App\Models\Scene;
use App\Models\SceneParticipant;
use App\Models\User;
use App\Scene\GameSessionService;
use Illuminate\Contracts\Broadcasting\Factory;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\Support\JoinsChronicle;
use Tests\TestCase;

class CombatSyncTest extends TestCase
{
    use JoinsChronicle;

    public function test_next_turn_updates_active_character_id(): void
    {
        $scene = Scene::factory()->active()->create();
        $scene->load('gameSession');
        $chronicleId = (int) $scene->gameSession->chronicle_id;

        $npc = $this->combatant($scene, $chronicleId, CharacterType::Npc, 5);
        $player = $this->combatant($scene, $chronicleId, CharacterType::Player, 1);
        $otherPlayer = Character::factory()->player()->create([
            'chronicle_id' => $chronicleId,
        ]);

        $service = app(CombatService::class);
        $encounter = $service->startEncounter($scene);
        $service->addParticipant($encounter, $npc);
        $service->addParticipant($encounter, $player);
        $service->rollInitiative($encounter);

        app(GameSessionService::class)->setActiveCharacter($scene->gameSession, $otherPlayer->id);

        $onPlayer = $service->nextTurn($encounter->refresh());

        $this->assertSame($player->id, $scene->gameSession->refresh()->active_character_id);
        $this->assertSame($player->id, $this->characterAtTurn($onPlayer));

        $onNpc = $service->nextTurn($onPlayer);

        $this->assertSame($npc->id, $this->characterAtTurn($onNpc));
        $this->assertNull($scene->gameSession->refresh()->active_character_id);
    }

    public function test_finish_clears_active_character_id(): void
    {
        $scene = Scene::factory()->active()->create();
        $scene->load('gameSession');
        $chronicleId = (int) $scene->gameSession->chronicle_id;
        $player = $this->combatant($scene, $chronicleId, CharacterType::Player, 3);

        $service = app(CombatService::class);
        $encounter = $service->startEncounter($scene);
        $service->addParticipant($encounter, $player);
        $service->rollInitiative($encounter);
        $service->nextTurn($encounter->refresh());
        $this->assertSame($player->id, $scene->gameSession->refresh()->active_character_id);

        $service->finishEncounter($encounter->refresh());

        $this->assertNull($scene->gameSession->refresh()->active_character_id);

        $restored = app(GameSessionService::class)->setActiveCharacter(
            $scene->gameSession->refresh(),
            $player->id,
        );
        $this->assertSame($player->id, $restored->active_character_id);

        $service->finishEncounter($encounter->refresh());
        $this->assertSame($player->id, $scene->gameSession->refresh()->active_character_id);
    }

    public function test_events_are_broadcast(): void
    {
        Event::fake([
            CombatStartedEvent::class,
            CombatTurnChangedEvent::class,
            CombatParticipantUpdatedEvent::class,
        ]);

        $scene = Scene::factory()->active()->create();
        $scene->load('gameSession');
        $chronicleId = (int) $scene->gameSession->chronicle_id;
        $player = $this->combatant($scene, $chronicleId, CharacterType::Player, 3);
        $service = app(CombatService::class);

        $encounter = $service->startEncounter($scene);
        $this->assertInstanceOf(ShouldBroadcastNow::class, new CombatStartedEvent(1, 1, 1));
        $this->assertInstanceOf(ShouldBroadcastNow::class, new CombatTurnChangedEvent(1, 1, 1, 1, 0, null, 'active'));
        $this->assertInstanceOf(ShouldBroadcastNow::class, new CombatParticipantUpdatedEvent(1, 1, 1, null));
        $this->assertBroadcastOnChronicle(
            CombatStartedEvent::class,
            $chronicleId,
            fn (CombatStartedEvent $event): bool => $event->sceneId === $scene->id
                && $event->combatEncounterId === $encounter->id
                && $event->broadcastAs() === 'CombatStarted',
        );

        $service->startEncounter($scene);
        Event::assertDispatchedTimes(CombatStartedEvent::class, 1);

        $participant = $service->addParticipant($encounter, $player, true);
        Event::assertDispatchedTimes(CombatParticipantUpdatedEvent::class, 1);
        $this->assertBroadcastOnChronicle(
            CombatParticipantUpdatedEvent::class,
            $chronicleId,
            fn (CombatParticipantUpdatedEvent $event): bool => $event->participantId === $participant->id
                && $event->combatEncounterId === $encounter->id,
        );

        $service->rollInitiative($encounter);
        $service->setInitiative($participant->refresh(), 8);
        $service->revealParticipant($participant->refresh());
        $service->setParticipantActive($participant->refresh(), false);
        Event::assertDispatchedTimes(CombatParticipantUpdatedEvent::class, 5);
        $this->assertBroadcastOnChronicle(
            CombatParticipantUpdatedEvent::class,
            $chronicleId,
            fn (CombatParticipantUpdatedEvent $event): bool => $event->combatEncounterId === $encounter->id
                && $event->broadcastAs() === 'CombatParticipantUpdated',
        );

        $service->setParticipantActive($participant->refresh(), true);
        $advanced = $service->nextTurn($encounter->refresh());
        $this->assertBroadcastOnChronicle(
            CombatTurnChangedEvent::class,
            $chronicleId,
            fn (CombatTurnChangedEvent $event): bool => $event->combatEncounterId === $advanced->id
                && $event->activeCharacterId === $player->id
                && $event->status === CombatEncounterStatus::Active->value
                && $event->broadcastAs() === 'CombatTurnChanged',
        );

        $service->finishEncounter($advanced->refresh());
        $this->assertBroadcastOnChronicle(
            CombatTurnChangedEvent::class,
            $chronicleId,
            fn (CombatTurnChangedEvent $event): bool => $event->combatEncounterId === $encounter->id
                && $event->activeCharacterId === null
                && $event->status === CombatEncounterStatus::Finished->value,
        );
    }

    public function test_chronicle_channel_allows_member_and_rejects_outsider(): void
    {
        $scene = Scene::factory()->active()->create();
        $scene->load('gameSession.chronicle');
        $member = User::factory()->create();
        $this->joinChronicle($member, $scene->gameSession->chronicle, ChronicleRole::Player);
        $outsider = User::factory()->create();
        $chronicleId = (string) $scene->gameSession->chronicle_id;

        $callback = app(Factory::class)
            ->connection()
            ->getChannels()
            ->get('chronicle.{chronicleId}');

        $this->assertIsCallable($callback);
        $this->assertTrue($callback($member, $chronicleId));
        $this->assertFalse($callback($outsider, $chronicleId));
    }

    public function test_diary_entries_get_combat_id(): void
    {
        $scene = Scene::factory()->active()->create();
        $scene->load('gameSession');
        $chronicleId = (int) $scene->gameSession->chronicle_id;
        $character = Character::factory()->npc()->create([
            'chronicle_id' => $chronicleId,
        ]);
        $encounter = app(CombatService::class)->startEncounter($scene);
        $vector = '['.implode(',', array_fill(0, 1024, '0.1')).']';

        $id = DB::table('character_diary_entries')->insertGetId([
            'character_id' => $character->id,
            'chronicle_id' => $chronicleId,
            'scene_id' => $scene->id,
            'combat_encounter_id' => $encounter->id,
            'level' => 0,
            'entry' => 'Заготовка связи боя и дневника.',
            'embedding' => DB::raw("'{$vector}'::vector"),
            'is_stale' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertSame(
            $encounter->id,
            (int) DB::table('character_diary_entries')->where('id', $id)->value('combat_encounter_id'),
        );
    }

    /**
     * @param  class-string  $event
     * @param  callable(object): bool  $predicate
     */
    private function assertBroadcastOnChronicle(string $event, int $chronicleId, callable $predicate): void
    {
        Event::assertDispatched($event, function (object $dispatched) use ($predicate, $chronicleId): bool {
            if (! $predicate($dispatched)) {
                return false;
            }

            $channels = $dispatched->broadcastOn();

            return $channels[0]->name === 'private-chronicle.'.$chronicleId;
        });
    }

    private function combatant(Scene $scene, int $chronicleId, CharacterType $type, int $dexterity): Character
    {
        $factory = Character::factory();
        $character = ($type === CharacterType::Player ? $factory->player() : $factory->npc())->create([
            'chronicle_id' => $chronicleId,
            'stats' => [
                'dexterity' => $this->dots($dexterity),
                'wits' => $this->dots(1),
            ],
        ]);
        SceneParticipant::factory()->create([
            'scene_id' => $scene->id,
            'character_id' => $character->id,
            'role' => $type === CharacterType::Player
                ? SceneParticipantRole::Player
                : SceneParticipantRole::Npc,
        ]);

        return $character;
    }

    private function characterAtTurn(CombatEncounter $encounter): int
    {
        $ordered = app(CombatService::class)->sortByInitiative(
            $encounter->participants()->with('character')->get(),
            $encounter->stat_keys ?? CombatService::DEFAULT_STAT_KEYS,
        );

        /** @var CombatParticipant $current */
        $current = $ordered->get($encounter->current_turn_index);

        return (int) $current->character_id;
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
