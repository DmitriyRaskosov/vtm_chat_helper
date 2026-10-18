<?php

namespace Tests\Feature;

use App\Enums\ChronicleRole;
use App\Models\CanonClan;
use App\Models\Chronicle;
use App\Models\ChronicleInvitation;
use App\Models\ChronicleMember;
use App\Models\GameSession;
use App\Models\Message;
use App\Models\Scene;
use App\Models\User;
use App\Models\WorldEntity;
use Database\Seeders\ChronicleSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DemoResetTest extends TestCase
{
    /** @var list<string> */
    private const DEMO_NPC_NAMES = [
        'Абрахам Леру',
        'Август Сент-Клер',
        'Гидеон Эш',
        'Ильзе Фогель',
        'Порижана',
    ];

    public function test_demo_reset_on_seeded_database_creates_demo_stand(): void
    {
        $canonClansBefore = CanonClan::query()->count();
        $usersBefore = User::query()->count();

        $this->assertSame(0, $this->exitCode('demo:reset'));

        $admin = User::query()->where('login', 'admin')->first();
        $this->assertNotNull($admin);
        $this->assertGreaterThanOrEqual($usersBefore, User::query()->count());

        $this->assertSame($canonClansBefore, CanonClan::query()->count());

        $chronicle = Chronicle::query()->where('title', 'Demo Chronicle')->first();
        $this->assertNotNull($chronicle);

        $this->assertDemoAdminMembership($admin, $chronicle);
        $this->assertSame(0, ChronicleInvitation::query()->count());

        $session = GameSession::query()->where('title', 'Demo Session')->where('chronicle_id', $chronicle->id)->first();
        $this->assertNotNull($session);

        $scene = Scene::query()->where('title', 'Встреча на пустыре')->where('game_session_id', $session->id)->first();
        $this->assertNotNull($scene);

        $npcNames = WorldEntity::query()
            ->where('chronicle_id', $chronicle->id)
            ->orderBy('canonical_name')
            ->pluck('canonical_name')
            ->all();

        foreach (self::DEMO_NPC_NAMES as $name) {
            $this->assertContains($name, $npcNames);
        }

        $this->assertSame(5, count(array_intersect(self::DEMO_NPC_NAMES, $npcNames)));
        $this->assertSame(0, Message::query()->count());
    }

    public function test_demo_reset_is_idempotent(): void
    {
        $this->assertSame(0, $this->exitCode('demo:reset'));

        $countsAfterFirst = $this->gameEntityCounts();

        $this->assertSame(0, $this->exitCode('demo:reset'));

        $this->assertSame($countsAfterFirst, $this->gameEntityCounts());
    }

    public function test_demo_reset_on_empty_game_data_after_fresh_seed(): void
    {
        Artisan::call('migrate:fresh', ['--seed' => true]);

        $this->assertDatabaseCount('chronicle_members', 0);

        $this->assertSame(0, $this->exitCode('demo:reset'));

        $this->assertSame(1, Chronicle::query()->where('title', 'Demo Chronicle')->count());
        $this->assertSame(5, WorldEntity::query()->where('entity_type', 'character')->count());
        $this->assertSame(1, ChronicleMember::query()->count());
        $this->assertSame(0, ChronicleInvitation::query()->count());
    }

    public function test_chronicle_seeder_without_users_creates_chronicle_without_membership(): void
    {
        $this->seed(ChronicleSeeder::class);

        $this->assertSame(1, Chronicle::query()->where('title', 'Моя первая хроника')->count());
        $this->assertDatabaseCount('chronicle_members', 0);
    }

    public function test_chronicle_seeder_with_admin_grants_storyteller_membership_listed_in_api(): void
    {
        $admin = User::factory()->create([
            'login' => 'admin',
            'password' => Hash::make('admin'),
        ]);

        $this->seed(ChronicleSeeder::class);

        $chronicle = Chronicle::query()->where('title', 'Моя первая хроника')->firstOrFail();
        $this->assertDemoAdminMembership($admin, $chronicle);

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/chronicles')
            ->assertOk()
            ->assertJsonCount(1, 'chronicles')
            ->assertJsonPath('chronicles.0.title', 'Моя первая хроника');
    }

    /**
     * @return array<string, int>
     */
    private function gameEntityCounts(): array
    {
        return [
            'chronicles' => Chronicle::query()->count(),
            'sessions' => GameSession::query()->count(),
            'scenes' => Scene::query()->count(),
            'world_entities' => WorldEntity::query()->count(),
            'messages' => Message::query()->count(),
            'chronicle_members' => ChronicleMember::query()->count(),
            'chronicle_invitations' => ChronicleInvitation::query()->count(),
        ];
    }

    private function assertDemoAdminMembership(User $admin, Chronicle $chronicle): void
    {
        $this->assertSame(1, ChronicleMember::query()->count());
        $this->assertDatabaseHas('chronicle_members', [
            'user_id' => $admin->id,
            'chronicle_id' => $chronicle->id,
            'role' => ChronicleRole::Storyteller->value,
            'left_at' => null,
        ]);
    }

    private function exitCode(string $command): int
    {
        return Artisan::call($command);
    }
}
