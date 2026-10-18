<?php

namespace Tests\Support;

use App\Models\CanonDiscipline;
use App\Models\Character;
use App\Models\CharacterDiaryEntry;
use App\Models\Chronicle;
use App\Models\ChronicleInvitation;
use App\Models\ChronicleMember;
use App\Models\CopilotRequest;
use App\Models\GameSession;
use App\Models\Message;
use App\Models\Scene;
use App\Models\SceneContext;
use App\Models\SceneParticipant;
use App\Models\User;
use Database\Seeders\CanonClanSeeder;
use Database\Seeders\CanonDisciplineSeeder;
use Database\Seeders\CanonSectSeeder;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\DB;

final class AccessMatrixFixtures
{
    public User $storyteller;

    public User $playerOwner;

    public User $playerForeign;

    public Chronicle $chronicle;

    public ChronicleInvitation $invitation;

    public GameSession $gameSession;

    public Scene $scene;

    public int $sceneContextRevision;

    public Character $npc;

    public Character $playerCharacter;

    public Character $archivedNpc;

    public Message $storytellerMessage;

    public Message $playerMessage;

    public Message $deletedPlayerMessage;

    public CharacterDiaryEntry $diaryEntry;

    public CopilotRequest $copilotRequest;

    public SceneParticipant $sceneParticipant;

    private function __construct() {}

    public static function create(TestCase $test): self
    {
        $test->seed(CanonSectSeeder::class);
        $test->seed(CanonClanSeeder::class);
        $test->seed(CanonDisciplineSeeder::class);

        $fixtures = new self;

        $fixtures->storyteller = User::factory()->create();
        $fixtures->playerOwner = User::factory()->create();
        $fixtures->playerForeign = User::factory()->create();

        $fixtures->chronicle = Chronicle::factory()->create([
            'title' => 'Матрица доступа',
            'created_by' => $fixtures->storyteller->id,
        ]);

        ChronicleMember::factory()
            ->for($fixtures->chronicle)
            ->for($fixtures->storyteller)
            ->storyteller()
            ->create();

        ChronicleMember::factory()
            ->for($fixtures->chronicle)
            ->for($fixtures->playerOwner)
            ->player()
            ->create();

        ChronicleMember::factory()
            ->for($fixtures->chronicle)
            ->for($fixtures->playerForeign)
            ->player()
            ->create();

        $fixtures->invitation = ChronicleInvitation::factory()->create([
            'chronicle_id' => $fixtures->chronicle->id,
            'created_by' => $fixtures->storyteller->id,
        ]);

        $fixtures->gameSession = GameSession::factory()->active()->create([
            'chronicle_id' => $fixtures->chronicle->id,
            'created_by' => $fixtures->storyteller->id,
        ]);

        $fixtures->scene = Scene::factory()->active()->forGameSession($fixtures->gameSession)->create();

        $context = SceneContext::query()->where('scene_id', $fixtures->scene->id)->first();
        $fixtures->sceneContextRevision = $context?->revision ?? 0;

        $fixtures->npc = Character::factory()->npc()->forChronicle($fixtures->chronicle)->create();
        $fixtures->playerCharacter = Character::factory()
            ->player($fixtures->playerOwner)
            ->forChronicle($fixtures->chronicle)
            ->create();
        $fixtures->archivedNpc = Character::factory()->npc()->forChronicle($fixtures->chronicle)->create([
            'is_active' => false,
        ]);

        $fixtures->storytellerMessage = Message::factory()->create([
            'scene_id' => $fixtures->scene->id,
            'user_id' => $fixtures->storyteller->id,
            'body' => 'Реплика рассказчика',
        ]);
        $fixtures->playerMessage = Message::factory()->create([
            'scene_id' => $fixtures->scene->id,
            'user_id' => $fixtures->playerOwner->id,
            'author_character_id' => $fixtures->playerCharacter->id,
            'body' => 'Реплика игрока',
        ]);
        $fixtures->deletedPlayerMessage = Message::factory()->create([
            'scene_id' => $fixtures->scene->id,
            'user_id' => $fixtures->playerOwner->id,
            'author_character_id' => $fixtures->playerCharacter->id,
            'body' => 'Удалённая реплика',
        ]);
        $fixtures->deletedPlayerMessage->delete();

        $fixtures->diaryEntry = $fixtures->createDiaryEntry($fixtures);

        $fixtures->copilotRequest = CopilotRequest::query()->create([
            'scene_id' => $fixtures->scene->id,
            'storyteller_id' => $fixtures->storyteller->id,
            'npc_name' => 'Тестовый NPC',
            'character_id' => $fixtures->npc->id,
            'prompt' => 'Тест',
            'drafts' => ['a', 'b', 'c'],
            'context_metadata' => [],
            'model' => 'fake',
            'prompt_version' => 'v1',
            'builder_version' => 'v1',
            'selected_draft_index' => null,
        ]);

        $fixtures->sceneParticipant = SceneParticipant::factory()->create([
            'scene_id' => $fixtures->scene->id,
            'character_id' => $fixtures->npc->id,
        ]);

        return $fixtures;
    }

    public function userForRole(string $role): ?User
    {
        return match ($role) {
            'guest' => null,
            'player_owner' => $this->playerOwner,
            'player_foreign' => $this->playerForeign,
            'storyteller' => $this->storyteller,
            default => throw new \InvalidArgumentException("Unknown role [{$role}]."),
        };
    }

    public function resolveUri(string $uriTemplate): string
    {
        $map = [
            '{scene}' => (string) $this->scene->id,
            '{chronicle}' => (string) $this->chronicle->id,
            '{invitation}' => (string) $this->invitation->id,
            '{token}' => 'missing-token',
            '{gameSession}' => (string) $this->gameSession->id,
            '{npc}' => (string) $this->npc->id,
            '{playerCharacter}' => (string) $this->playerCharacter->id,
            '{archivedNpc}' => (string) $this->archivedNpc->id,
            '{storytellerMessage}' => (string) $this->storytellerMessage->id,
            '{playerMessage}' => (string) $this->playerMessage->id,
            '{deletedPlayerMessage}' => (string) $this->deletedPlayerMessage->id,
            '{diaryEntry}' => (string) $this->diaryEntry->id,
            '{character}' => (string) $this->npc->id,
            '{message}' => (string) $this->storytellerMessage->id,
        ];

        $path = $uriTemplate;
        foreach ($map as $placeholder => $value) {
            $path = str_replace($placeholder, $value, $path);
        }

        if (preg_match('/\{[^}]+\}/', $path)) {
            throw new \RuntimeException("Unresolved placeholders in URI [{$path}].");
        }

        return '/api/'.$path;
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(string $method, string $uriTemplate, string $role): array
    {
        if ($method === 'POST' && $uriTemplate === 'register') {
            $suffix = substr(md5($role.microtime(true)), 0, 8);

            return [
                'name' => 'Новый '.$role,
                'login' => 'matrix_'.$suffix,
                'password' => 'secret123',
                'password_confirmation' => 'secret123',
            ];
        }

        if ($method === 'POST' && $uriTemplate === 'login') {
            return [
                'login' => $this->playerOwner->login,
                'password' => 'password',
            ];
        }

        if ($method === 'POST' && $uriTemplate === 'chronicles/{chronicle}/messages') {
            if ($role === 'storyteller' || $role === 'player_foreign') {
                return [
                    'scene_id' => $this->scene->id,
                    'character_id' => $this->npc->id,
                    'body' => 'Тест матрицы',
                ];
            }

            return [
                'scene_id' => $this->scene->id,
                'character_id' => $this->playerCharacter->id,
                'body' => 'Тест матрицы',
            ];
        }

        if ($method === 'POST' && $uriTemplate === 'chronicles/{chronicle}/characters') {
            return [
                'canonical_name' => 'Новый персонаж',
                'character_type' => 'npc',
            ];
        }

        if ($method === 'POST' && $uriTemplate === 'chronicles') {
            return ['title' => 'Хроника матрицы'];
        }

        if ($method === 'POST' && $uriTemplate === 'chronicles/{chronicle}/invitations') {
            return [
                'role' => 'player',
                'expires_at' => now()->addDay()->toIso8601String(),
                'max_uses' => 1,
            ];
        }

        if ($method === 'POST' && $uriTemplate === 'chronicles/{chronicle}/game-sessions') {
            return ['title' => 'Новая сессия'];
        }

        if ($method === 'POST' && $uriTemplate === 'game-sessions/{gameSession}/scenes') {
            return ['title' => 'Дополнительная сцена', 'activate' => false];
        }

        if ($method === 'PATCH' && str_ends_with($uriTemplate, '/ooc')) {
            return ['is_ooc' => true];
        }

        if ($method === 'PUT' && str_contains($uriTemplate, '/context')) {
            return [
                'expected_revision' => $this->sceneContextRevision,
                'atmosphere' => 'Тест',
            ];
        }

        if ($method === 'PATCH' && str_contains($uriTemplate, '/characters/{playerCharacter}')) {
            return ['concept' => 'Обновлённый концепт'];
        }

        if ($method === 'PATCH' && str_contains($uriTemplate, '/characters/{npc}')) {
            return ['concept' => 'Обновлённый NPC'];
        }

        if ($method === 'PUT' && str_contains($uriTemplate, '/biography')) {
            return ['full_text' => 'Текст биографии для матрицы доступа.'];
        }

        if ($method === 'PUT' && str_contains($uriTemplate, '/disciplines')) {
            $disciplineId = (int) CanonDiscipline::query()->value('id');

            return [
                'disciplines' => [
                    ['discipline_id' => $disciplineId, 'level' => 1],
                ],
            ];
        }

        if ($method === 'PUT' && str_contains($uriTemplate, '/traits')) {
            return ['traits' => []];
        }

        if ($method === 'PUT' && str_contains($uriTemplate, '/place')) {
            return ['location_entity_id' => null];
        }

        if ($method === 'POST' && str_contains($uriTemplate, '/diary/preview')) {
            return ['prompt' => 'Превью'];
        }

        if ($method === 'PATCH' && str_contains($uriTemplate, 'diary-entries/{diaryEntry}')) {
            return ['entry' => 'Обновлённая запись дневника.'];
        }

        if ($method === 'POST' && $uriTemplate === 'chronicles/{chronicle}/copilot/drafts') {
            return [
                'character_id' => $this->npc->id,
                'prompt' => 'Синопсис для Copilot',
                'scene_id' => $this->scene->id,
            ];
        }

        if ($method === 'POST' && str_contains($uriTemplate, '/participants')) {
            return [
                'character_id' => $this->playerCharacter->id,
                'role' => 'player',
            ];
        }

        return [];
    }

    private function createDiaryEntry(self $fixtures): CharacterDiaryEntry
    {
        $vector = '['.implode(',', array_fill(0, 1024, '0.1')).']';

        $id = DB::table('character_diary_entries')->insertGetId([
            'character_id' => $fixtures->npc->id,
            'chronicle_id' => $fixtures->chronicle->id,
            'scene_id' => $fixtures->scene->id,
            'level' => 0,
            'from_message_id' => $fixtures->storytellerMessage->id,
            'to_message_id' => $fixtures->storytellerMessage->id,
            'entry' => 'Запись дневника для матрицы.',
            'importance' => null,
            'embedding' => DB::raw("'{$vector}'::vector"),
            'is_stale' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return CharacterDiaryEntry::query()->findOrFail($id);
    }
}
