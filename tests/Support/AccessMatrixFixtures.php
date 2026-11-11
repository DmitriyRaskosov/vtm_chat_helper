<?php

namespace Tests\Support;

use App\Enums\CombatEncounterStatus;
use App\Models\CanonClan;
use App\Models\CanonDiscipline;
use App\Models\Character;
use App\Models\CharacterDiaryEntry;
use App\Models\CharacterItem;
use App\Models\Chronicle;
use App\Models\ChronicleInvitation;
use App\Models\ChronicleLocation;
use App\Models\ChronicleMember;
use App\Models\CombatEncounter;
use App\Models\CombatParticipant;
use App\Models\CopilotRequest;
use App\Models\DiceMacro;
use App\Models\GameSession;
use App\Models\LocationNode;
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
use Illuminate\Support\Facades\Storage;

final class AccessMatrixFixtures
{
    public User $storyteller;

    public User $playerOwner;

    public User $playerForeign;

    public User $playerOtherChronicle;

    public Chronicle $chronicle;

    public Chronicle $otherChronicle;

    public ChronicleInvitation $invitation;

    public string $invitationAcceptToken;

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

    public CombatEncounter $combatEncounter;

    public CombatParticipant $combatParticipant;

    public ChronicleLocation $chronicleLocation;

    public CharacterItem $playerItem;

    public DiceMacro $diceMacro;

    private ?LocationNode $locationNodeCache = null;

    private function __construct() {}

    public static function create(TestCase $test): self
    {
        $test->seed(CanonSectSeeder::class);
        $test->seed(CanonClanSeeder::class);
        $test->seed(CanonDisciplineSeeder::class);

        Storage::fake('public');

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

        $fixtures->playerOtherChronicle = User::factory()->create();

        $fixtures->otherChronicle = Chronicle::factory()->create([
            'title' => 'Чужая хроника матрицы',
            'created_by' => $fixtures->storyteller->id,
        ]);

        ChronicleMember::factory()
            ->for($fixtures->otherChronicle)
            ->for($fixtures->playerOtherChronicle)
            ->player()
            ->create();

        $fixtures->invitationAcceptToken = 'access-matrix-invite-token';
        $fixtures->invitation = ChronicleInvitation::factory()->create([
            'chronicle_id' => $fixtures->chronicle->id,
            'created_by' => $fixtures->storyteller->id,
            'token_hash' => hash('sha256', $fixtures->invitationAcceptToken),
        ]);

        $fixtures->gameSession = GameSession::factory()->active()->create([
            'chronicle_id' => $fixtures->chronicle->id,
            'created_by' => $fixtures->storyteller->id,
        ]);

        $fixtures->scene = Scene::factory()->active()->forGameSession($fixtures->gameSession)->create();

        $context = SceneContext::query()->where('scene_id', $fixtures->scene->id)->first();
        $fixtures->sceneContextRevision = $context?->revision ?? 0;

        $fixtures->chronicleLocation = ChronicleLocation::query()->create([
            'chronicle_id' => $fixtures->chronicle->id,
            'name' => 'Локация матрицы',
            'description' => 'Описание для матрицы доступа',
        ]);

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

        $fixtures->combatEncounter = CombatEncounter::query()->create([
            'scene_id' => $fixtures->scene->id,
            'status' => CombatEncounterStatus::Active,
            'current_round' => 1,
            'current_turn_index' => 0,
            'started_at' => now(),
        ]);
        $fixtures->combatParticipant = CombatParticipant::query()->create([
            'combat_encounter_id' => $fixtures->combatEncounter->id,
            'character_id' => $fixtures->npc->id,
            'initiative' => 1,
            'is_active' => true,
            'is_hidden' => false,
        ]);

        $fixtures->playerItem = CharacterItem::query()->create([
            'character_id' => $fixtures->playerCharacter->id,
            'name' => 'Трость',
            'quantity' => 1,
            'is_equipped' => false,
        ]);

        $fixtures->diceMacro = DiceMacro::query()->create([
            'user_id' => $fixtures->playerOwner->id,
            'name' => 'Макрос матрицы',
            'pool' => 1,
            'difficulty' => 6,
            'dice_type' => 10,
            'use_willpower' => false,
            'stats' => [],
        ]);

        $fixtures->seedMatrixImageFiles();

        return $fixtures;
    }

    private function seedMatrixImageFiles(): void
    {
        $png = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==',
            true,
        );

        $sceneImagePath = 'scenes/'.$this->scene->id.'/location.png';
        Storage::disk('public')->put($sceneImagePath, $png);
        $context = SceneContext::query()->firstOrCreate(
            ['scene_id' => $this->scene->id],
            [
                'chronicle_id' => $this->chronicle->id,
                'revision' => 0,
            ],
        );
        $context->location_image_path = $sceneImagePath;
        $context->save();

        $locationImagePath = 'locations/'.$this->chronicleLocation->id.'/image.png';
        Storage::disk('public')->put($locationImagePath, $png);
        $this->chronicleLocation->image_path = $locationImagePath;
        $this->chronicleLocation->save();

        foreach ([$this->npc, $this->playerCharacter] as $character) {
            $sheetPath = 'characters/'.$character->id.'/sheet.png';
            Storage::disk('public')->put($sheetPath, $png);
            $character->sheet_image_path = $sheetPath;
            $character->save();
        }
    }

    public function ensureLocationNode(): LocationNode
    {
        if ($this->locationNodeCache instanceof LocationNode) {
            return $this->locationNodeCache;
        }

        $vector = '['.implode(',', array_fill(0, 1024, '0.2')).']';
        $locationNodeId = DB::table('location_nodes')->insertGetId([
            'chronicle_location_id' => $this->chronicleLocation->id,
            'parent_id' => null,
            'name' => 'Объект матрицы',
            'description' => 'Для CRUD',
            'embedding' => DB::raw("'{$vector}'::vector"),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $this->locationNodeCache = LocationNode::query()->findOrFail($locationNodeId);
    }

    public function userForRole(string $role): ?User
    {
        return match ($role) {
            'guest' => null,
            'player_owner' => $this->playerOwner,
            'player_foreign' => $this->playerForeign,
            'player_other_chronicle' => $this->playerOtherChronicle,
            'storyteller' => $this->storyteller,
            default => throw new \InvalidArgumentException("Unknown role [{$role}]."),
        };
    }

    public function resolveUri(string $uriTemplate): string
    {
        $map = [
            '{scene}' => (string) $this->scene->id,
            '{combat}' => (string) $this->combatEncounter->id,
            '{participant}' => (string) $this->combatParticipant->id,
            '{chronicle}' => (string) $this->chronicle->id,
            '{invitation}' => (string) $this->invitation->id,
            '{token}' => $this->invitationAcceptToken,
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
            '{chronicleLocation}' => (string) $this->chronicleLocation->id,
            '{item}' => (string) $this->playerItem->id,
            '{macro}' => (string) $this->diceMacro->id,
            '{key}' => 'copilot.topics.system',
        ];

        if (str_contains($uriTemplate, '{locationNode}')) {
            $map['{locationNode}'] = (string) $this->ensureLocationNode()->id;
        }

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
                'clan_id' => CanonClan::query()->where('slug', 'tremere')->value('id'),
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

        if ($method === 'PATCH' && $uriTemplate === 'chronicles/{chronicle}/time') {
            return ['datetime' => '1999-10-31T23:00:00Z'];
        }

        if ($method === 'POST' && $uriTemplate === 'chronicles/{chronicle}/locations') {
            return ['name' => 'Локация матрицы POST'];
        }

        if ($method === 'PATCH' && $uriTemplate === 'locations/{chronicleLocation}') {
            return ['name' => 'Локация матрицы PATCH'];
        }

        if ($method === 'POST' && $uriTemplate === 'chronicles/{chronicle}/scenes/{scene}/context/apply-template') {
            return [
                'location_id' => $this->chronicleLocation->id,
                'expected_revision' => $this->sceneContextRevision,
            ];
        }

        if ($method === 'PATCH' && $uriTemplate === 'game-sessions/{gameSession}/turn') {
            return [
                'character_id' => $this->playerCharacter->id,
            ];
        }

        if ($method === 'PATCH' && $uriTemplate === 'combat/{combat}/participants/{participant}/active') {
            return ['is_active' => true];
        }

        if ($method === 'POST' && $uriTemplate === 'combat/{combat}/participants/{participant}/initiative') {
            return ['initiative' => 5];
        }

        if ($method === 'POST' && $uriTemplate === 'combat/{combat}/participants') {
            return [
                'character_id' => $this->playerCharacter->id,
                'is_hidden' => false,
            ];
        }

        if ($method === 'POST' && $uriTemplate === 'game-sessions/{gameSession}/scenes') {
            return ['title' => 'Дополнительная сцена', 'activate' => false];
        }

        if ($method === 'PATCH' && str_ends_with($uriTemplate, '/ooc')) {
            return ['is_ooc' => true];
        }

        if ($method === 'PATCH' && str_starts_with($uriTemplate, 'messages/{')) {
            return ['body' => 'Обновлённый текст сообщения.'];
        }

        if ($method === 'PUT' && $uriTemplate === 'workshop/prompts/{key}') {
            return ['content' => 'Текст промпта для матрицы доступа.'];
        }

        if ($method === 'PUT' && str_contains($uriTemplate, '/note')) {
            return [
                'body' => 'Заметка матрицы доступа',
            ];
        }

        if ($method === 'PUT' && str_contains($uriTemplate, '/context')) {
            return [
                'expected_revision' => $this->sceneContextRevision,
                'atmosphere' => 'Тест',
            ];
        }

        if (str_contains($uriTemplate, '/items')) {
            return ['name' => 'Предмет матрицы'];
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

        if ($method === 'PUT' && str_contains($uriTemplate, '/place')) {
            return ['location_entity_id' => null];
        }

        if ($method === 'PATCH' && str_contains($uriTemplate, '/stats')) {
            return [
                'stats' => [
                    'strength' => ['current' => 1, 'is_temporary' => false],
                ],
            ];
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

        if ($uriTemplate === 'dice-macros' && $method === 'POST') {
            return [
                'name' => 'Макрос матрицы',
                'pool' => 1,
                'difficulty' => 6,
            ];
        }

        if ($uriTemplate === 'dice-macros/{macro}' && $method === 'PATCH') {
            return [
                'name' => 'Макрос матрицы PATCH',
            ];
        }

        if ($method === 'POST' && $uriTemplate === 'chronicles/{chronicle}/scenes/{scene}/dice-rolls') {
            return [
                'pool' => 1,
                'difficulty' => 6,
            ];
        }

        if ($method === 'POST' && $uriTemplate === 'chronicles/{chronicle}/scenes/{scene}/events/generate') {
            return [
                'action_description' => 'Удар когтями',
                'attack_successes' => 2,
                'defense_successes' => 1,
            ];
        }

        if ($method === 'POST' && $uriTemplate === 'locations/{chronicleLocation}/location-nodes/parse') {
            return [
                'environment_details' => 'Текст окружения для парсинга.',
            ];
        }

        if ($method === 'PATCH' && $uriTemplate === 'locations/{chronicleLocation}/location-nodes/{locationNode}') {
            return ['name' => 'Объект матрицы PATCH'];
        }

        if ($method === 'POST' && str_contains($uriTemplate, '/participants')) {
            return [
                'character_id' => $this->playerCharacter->id,
                'role' => 'player',
            ];
        }

        if ($method === 'POST' && $uriTemplate === 'scenes/{scene}/typing') {
            return ['is_ooc' => false];
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
