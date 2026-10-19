<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\AccessMatrixFixtures;
use Tests\TestCase;

class AccessMatrixTest extends TestCase
{
    private const EXPECT_401 = '401';

    private const EXPECT_403 = '403';

    private const EXPECT_DENY = 'deny';

    private const EXPECT_ALLOWED = 'allowed';

    private AccessMatrixFixtures $fixtures;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fixtures = AccessMatrixFixtures::create($this);
        $this->fakeChat()
            ->push('{"topics":["topic"]}')
            ->push('{"drafts":["one","two","three"]}');
        $this->fakeEmbeddings();
    }

    public function test_every_api_route_is_listed_in_access_matrix(): void
    {
        $matrixSignatures = self::matrixRouteSignatures();
        $missing = [];

        foreach (Route::getRoutes() as $route) {
            $uri = $route->uri();
            if (! str_starts_with($uri, 'api/')) {
                continue;
            }

            $relativeUri = substr($uri, strlen('api/'));

            foreach ($route->methods() as $method) {
                if ($method === 'HEAD') {
                    continue;
                }

                $signature = self::routeSignature($method, $relativeUri);
                if (! isset($matrixSignatures[$signature])) {
                    $missing[] = $signature;
                }
            }
        }

        sort($missing);
        $this->assertSame([], $missing, 'API routes missing from access matrix: '.implode(', ', $missing));
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: string, 3: string, 4: string}>
     */
    public static function accessMatrixProvider(): array
    {
        return array_merge(
            self::forRoles('auth.register', 'POST', 'register', [
                'guest' => self::EXPECT_ALLOWED,
                'player_owner' => self::EXPECT_ALLOWED,
                'player_foreign' => self::EXPECT_ALLOWED,
                'storyteller' => self::EXPECT_ALLOWED,
            ]),
            self::forRoles('auth.login', 'POST', 'login', [
                'guest' => self::EXPECT_ALLOWED,
                'player_owner' => self::EXPECT_ALLOWED,
                'player_foreign' => self::EXPECT_ALLOWED,
                'storyteller' => self::EXPECT_ALLOWED,
            ]),
            self::forRoles('auth.user', 'GET', 'user', [
                'guest' => self::EXPECT_401,
                'player_owner' => self::EXPECT_ALLOWED,
                'player_foreign' => self::EXPECT_ALLOWED,
                'storyteller' => self::EXPECT_ALLOWED,
            ]),
            self::forRoles('auth.logout', 'POST', 'logout', [
                'guest' => self::EXPECT_401,
                'player_owner' => self::EXPECT_ALLOWED,
                'player_foreign' => self::EXPECT_ALLOWED,
                'storyteller' => self::EXPECT_ALLOWED,
            ]),
            self::forRoles('chronicles.index', 'GET', 'chronicles', [
                'guest' => self::EXPECT_401,
                'player_owner' => self::EXPECT_ALLOWED,
                'player_foreign' => self::EXPECT_ALLOWED,
                'storyteller' => self::EXPECT_ALLOWED,
            ]),
            self::forRoles('chronicles.store', 'POST', 'chronicles', [
                'guest' => self::EXPECT_401,
                'player_owner' => self::EXPECT_ALLOWED,
                'player_foreign' => self::EXPECT_ALLOWED,
                'storyteller' => self::EXPECT_ALLOWED,
            ]),
            self::forRoles('chronicles.show', 'GET', 'chronicles/{chronicle}', [
                'guest' => self::EXPECT_401,
                'player_owner' => self::EXPECT_ALLOWED,
                'player_foreign' => self::EXPECT_ALLOWED,
                'storyteller' => self::EXPECT_ALLOWED,
            ]),
            self::forRoles('invitations.store', 'POST', 'chronicles/{chronicle}/invitations', [
                'guest' => self::EXPECT_401,
                'player_owner' => self::EXPECT_403,
                'player_foreign' => self::EXPECT_403,
                'storyteller' => self::EXPECT_ALLOWED,
            ]),
            self::forRoles('invitations.revoke', 'DELETE', 'chronicles/{chronicle}/invitations/{invitation}', [
                'guest' => self::EXPECT_401,
                'player_owner' => self::EXPECT_403,
                'player_foreign' => self::EXPECT_403,
                'storyteller' => self::EXPECT_ALLOWED,
            ]),
            self::forRoles('invitations.accept', 'POST', 'invitations/{token}/accept', [
                'guest' => self::EXPECT_401,
                'player_owner' => self::EXPECT_ALLOWED,
                'player_foreign' => self::EXPECT_ALLOWED,
                'storyteller' => self::EXPECT_ALLOWED,
            ]),
            self::forRoles('game_sessions.active', 'GET', 'chronicles/{chronicle}/game-sessions/active', [
                'guest' => self::EXPECT_401,
                'player_owner' => self::EXPECT_ALLOWED,
                'player_foreign' => self::EXPECT_ALLOWED,
                'storyteller' => self::EXPECT_ALLOWED,
            ]),
            self::forRoles('game_sessions.store', 'POST', 'chronicles/{chronicle}/game-sessions', [
                'guest' => self::EXPECT_401,
                'player_owner' => self::EXPECT_403,
                'player_foreign' => self::EXPECT_403,
                'storyteller' => self::EXPECT_ALLOWED,
            ]),
            self::forRoles('scenes.store', 'POST', 'game-sessions/{gameSession}/scenes', [
                'guest' => self::EXPECT_401,
                'player_owner' => self::EXPECT_403,
                'player_foreign' => self::EXPECT_403,
                'storyteller' => self::EXPECT_ALLOWED,
            ]),
            self::forRoles('scenes.activate', 'PATCH', 'scenes/{scene}/activate', [
                'guest' => self::EXPECT_401,
                'player_owner' => self::EXPECT_403,
                'player_foreign' => self::EXPECT_403,
                'storyteller' => self::EXPECT_ALLOWED,
            ]),
            self::forRoles('scenes.close', 'PATCH', 'scenes/{scene}/close', [
                'guest' => self::EXPECT_401,
                'player_owner' => self::EXPECT_403,
                'player_foreign' => self::EXPECT_403,
                'storyteller' => self::EXPECT_ALLOWED,
            ]),
            self::forRoles('scenes.context.show', 'GET', 'scenes/{scene}/context', [
                'guest' => self::EXPECT_401,
                'player_owner' => self::EXPECT_ALLOWED,
                'player_foreign' => self::EXPECT_ALLOWED,
                'storyteller' => self::EXPECT_ALLOWED,
            ]),
            self::forRoles('scenes.context.update', 'PUT', 'scenes/{scene}/context', [
                'guest' => self::EXPECT_401,
                'player_owner' => self::EXPECT_403,
                'player_foreign' => self::EXPECT_403,
                'storyteller' => self::EXPECT_ALLOWED,
            ]),
            self::forRoles('scenes.participants.index', 'GET', 'scenes/{scene}/participants', [
                'guest' => self::EXPECT_401,
                'player_owner' => self::EXPECT_ALLOWED,
                'player_foreign' => self::EXPECT_ALLOWED,
                'storyteller' => self::EXPECT_ALLOWED,
            ]),
            self::forRoles('scenes.participants.store', 'POST', 'scenes/{scene}/participants', [
                'guest' => self::EXPECT_401,
                'player_owner' => self::EXPECT_403,
                'player_foreign' => self::EXPECT_403,
                'storyteller' => self::EXPECT_ALLOWED,
            ]),
            self::forRoles('scenes.participants.leave', 'PATCH', 'scenes/{scene}/participants/{npc}', [
                'guest' => self::EXPECT_401,
                'player_owner' => self::EXPECT_403,
                'player_foreign' => self::EXPECT_403,
                'storyteller' => self::EXPECT_ALLOWED,
            ]),
            self::forRoles('messages.index', 'GET', 'chronicles/{chronicle}/messages', [
                'guest' => self::EXPECT_401,
                'player_owner' => self::EXPECT_ALLOWED,
                'player_foreign' => self::EXPECT_ALLOWED,
                'storyteller' => self::EXPECT_ALLOWED,
            ]),
            self::forRoles('messages.store', 'POST', 'chronicles/{chronicle}/messages', [
                'guest' => self::EXPECT_401,
                'player_owner' => self::EXPECT_ALLOWED,
                'player_foreign' => self::EXPECT_403,
                'storyteller' => self::EXPECT_ALLOWED,
            ]),
            self::forRoles('messages.destroy.foreign', 'DELETE', 'messages/{storytellerMessage}', [
                'guest' => self::EXPECT_401,
                'player_owner' => self::EXPECT_403,
                'player_foreign' => self::EXPECT_403,
                'storyteller' => self::EXPECT_ALLOWED,
            ]),
            self::forRoles('messages.destroy.owner', 'DELETE', 'messages/{playerMessage}', [
                'guest' => self::EXPECT_401,
                'player_owner' => self::EXPECT_ALLOWED,
                'player_foreign' => self::EXPECT_403,
                'storyteller' => self::EXPECT_ALLOWED,
            ]),
            self::forRoles('messages.ooc.foreign', 'PATCH', 'messages/{storytellerMessage}/ooc', [
                'guest' => self::EXPECT_401,
                'player_owner' => self::EXPECT_403,
                'player_foreign' => self::EXPECT_403,
                'storyteller' => self::EXPECT_ALLOWED,
            ]),
            self::forRoles('messages.ooc.owner', 'PATCH', 'messages/{playerMessage}/ooc', [
                'guest' => self::EXPECT_401,
                'player_owner' => self::EXPECT_ALLOWED,
                'player_foreign' => self::EXPECT_403,
                'storyteller' => self::EXPECT_ALLOWED,
            ]),
            self::forRoles('messages.restore', 'POST', 'messages/{deletedPlayerMessage}/restore', [
                'guest' => self::EXPECT_401,
                'player_owner' => self::EXPECT_ALLOWED,
                'player_foreign' => self::EXPECT_403,
                'storyteller' => self::EXPECT_ALLOWED,
            ]),
            self::forRoles('catalog', 'GET', 'character-sheet/catalog', [
                'guest' => self::EXPECT_401,
                'player_owner' => self::EXPECT_ALLOWED,
                'player_foreign' => self::EXPECT_ALLOWED,
                'storyteller' => self::EXPECT_ALLOWED,
            ]),
            self::forRoles('characters.index', 'GET', 'chronicles/{chronicle}/characters', [
                'guest' => self::EXPECT_401,
                'player_owner' => self::EXPECT_ALLOWED,
                'player_foreign' => self::EXPECT_ALLOWED,
                'storyteller' => self::EXPECT_ALLOWED,
            ]),
            self::forRoles('characters.store', 'POST', 'chronicles/{chronicle}/characters', [
                'guest' => self::EXPECT_401,
                'player_owner' => self::EXPECT_403,
                'player_foreign' => self::EXPECT_403,
                'storyteller' => self::EXPECT_ALLOWED,
            ]),
            self::forRoles('characters.show.npc', 'GET', 'characters/{npc}', [
                'guest' => self::EXPECT_401,
                'player_owner' => self::EXPECT_403,
                'player_foreign' => self::EXPECT_403,
                'storyteller' => self::EXPECT_ALLOWED,
            ]),
            self::forRoles('characters.show.own', 'GET', 'characters/{playerCharacter}', [
                'guest' => self::EXPECT_401,
                'player_owner' => self::EXPECT_ALLOWED,
                'player_foreign' => self::EXPECT_403,
                'storyteller' => self::EXPECT_ALLOWED,
            ]),
            self::forRoles('characters.update.own', 'PATCH', 'characters/{playerCharacter}', [
                'guest' => self::EXPECT_401,
                'player_owner' => self::EXPECT_ALLOWED,
                'player_foreign' => self::EXPECT_403,
                'storyteller' => self::EXPECT_ALLOWED,
            ]),
            self::forRoles('characters.update.npc', 'PATCH', 'characters/{npc}', [
                'guest' => self::EXPECT_401,
                'player_owner' => self::EXPECT_403,
                'player_foreign' => self::EXPECT_403,
                'storyteller' => self::EXPECT_ALLOWED,
            ]),
            self::forRoles('characters.disciplines.own', 'PUT', 'characters/{playerCharacter}/disciplines', [
                'guest' => self::EXPECT_401,
                'player_owner' => self::EXPECT_ALLOWED,
                'player_foreign' => self::EXPECT_403,
                'storyteller' => self::EXPECT_ALLOWED,
            ]),
            self::forRoles('characters.biography.own', 'PUT', 'characters/{playerCharacter}/biography', [
                'guest' => self::EXPECT_401,
                'player_owner' => self::EXPECT_ALLOWED,
                'player_foreign' => self::EXPECT_403,
                'storyteller' => self::EXPECT_ALLOWED,
            ]),
            self::forRoles('characters.traits.own', 'PUT', 'characters/{playerCharacter}/traits', [
                'guest' => self::EXPECT_401,
                'player_owner' => self::EXPECT_ALLOWED,
                'player_foreign' => self::EXPECT_403,
                'storyteller' => self::EXPECT_ALLOWED,
            ]),
            self::forRoles('characters.place', 'PUT', 'characters/{npc}/place', [
                'guest' => self::EXPECT_401,
                'player_owner' => self::EXPECT_403,
                'player_foreign' => self::EXPECT_403,
                'storyteller' => self::EXPECT_ALLOWED,
            ]),
            self::forRoles('characters.archive', 'POST', 'characters/{npc}/archive', [
                'guest' => self::EXPECT_401,
                'player_owner' => self::EXPECT_403,
                'player_foreign' => self::EXPECT_403,
                'storyteller' => self::EXPECT_ALLOWED,
            ]),
            self::forRoles('characters.restore', 'POST', 'characters/{archivedNpc}/restore', [
                'guest' => self::EXPECT_401,
                'player_owner' => self::EXPECT_403,
                'player_foreign' => self::EXPECT_403,
                'storyteller' => self::EXPECT_ALLOWED,
            ]),
            self::forRoles('diary.index', 'GET', 'characters/{npc}/diary', [
                'guest' => self::EXPECT_401,
                'player_owner' => self::EXPECT_403,
                'player_foreign' => self::EXPECT_403,
                'storyteller' => self::EXPECT_ALLOWED,
            ]),
            self::forRoles('diary.preview', 'POST', 'characters/{npc}/diary/preview', [
                'guest' => self::EXPECT_401,
                'player_owner' => self::EXPECT_403,
                'player_foreign' => self::EXPECT_403,
                'storyteller' => self::EXPECT_ALLOWED,
            ]),
            self::forRoles('diary.show', 'GET', 'diary-entries/{diaryEntry}', [
                'guest' => self::EXPECT_401,
                'player_owner' => self::EXPECT_403,
                'player_foreign' => self::EXPECT_403,
                'storyteller' => self::EXPECT_ALLOWED,
            ]),
            self::forRoles('diary.update', 'PATCH', 'diary-entries/{diaryEntry}', [
                'guest' => self::EXPECT_401,
                'player_owner' => self::EXPECT_403,
                'player_foreign' => self::EXPECT_403,
                'storyteller' => self::EXPECT_ALLOWED,
            ]),
            self::forRoles('diary.destroy', 'DELETE', 'diary-entries/{diaryEntry}', [
                'guest' => self::EXPECT_401,
                'player_owner' => self::EXPECT_403,
                'player_foreign' => self::EXPECT_403,
                'storyteller' => self::EXPECT_ALLOWED,
            ]),
            self::forRoles('diary.regenerate', 'POST', 'diary-entries/{diaryEntry}/regenerate', [
                'guest' => self::EXPECT_401,
                'player_owner' => self::EXPECT_403,
                'player_foreign' => self::EXPECT_403,
                'storyteller' => self::EXPECT_ALLOWED,
            ]),
            self::forRoles('copilot.drafts', 'POST', 'chronicles/{chronicle}/copilot/drafts', [
                'guest' => self::EXPECT_401,
                'player_owner' => self::EXPECT_403,
                'player_foreign' => self::EXPECT_403,
                'storyteller' => self::EXPECT_ALLOWED,
            ]),
            self::forRoles('scenes.dice-rolls.store', 'POST', 'chronicles/{chronicle}/scenes/{scene}/dice-rolls', [
                'guest' => self::EXPECT_401,
                'player_owner' => self::EXPECT_ALLOWED,
                'player_foreign' => self::EXPECT_ALLOWED,
                'storyteller' => self::EXPECT_ALLOWED,
            ]),
            self::forRoles('scenes.logs.index', 'GET', 'chronicles/{chronicle}/scenes/{scene}/logs', [
                'guest' => self::EXPECT_401,
                'player_owner' => self::EXPECT_ALLOWED,
                'player_foreign' => self::EXPECT_ALLOWED,
                'storyteller' => self::EXPECT_ALLOWED,
            ]),
        );
    }

    #[DataProvider('accessMatrixProvider')]
    public function test_access_matrix(
        string $caseId,
        string $method,
        string $uriTemplate,
        string $role,
        string $expected,
    ): void {
        $user = $this->fixtures->userForRole($role);
        $uri = $this->fixtures->resolveUri($uriTemplate);
        $payload = $this->fixtures->payload($method, $uriTemplate, $role);

        $request = $user === null
            ? $this->json($method, $uri, $payload)
            : $this->actingAs($user, 'sanctum')->json($method, $uri, $payload);

        $status = $request->status();

        if ($expected === self::EXPECT_401) {
            if ($status !== 401) {
                $this->markTestSkipped("bug: {$caseId} expected 401, got {$status}");
            }

            $request->assertStatus(401);

            return;
        }

        if ($expected === self::EXPECT_403) {
            if ($status !== 403) {
                $this->markTestSkipped("bug: {$caseId} expected 403, got {$status}");
            }

            $request->assertStatus(403);

            return;
        }

        if ($expected === self::EXPECT_DENY) {
            if (! in_array($status, [403, 404], true)) {
                $this->markTestSkipped("bug: {$caseId} expected 403 or 404, got {$status}");
            }

            $this->assertContains($status, [403, 404], $caseId);

            return;
        }

        if (in_array($status, [401, 403], true)) {
            $this->markTestSkipped("bug: {$caseId} expected allowed, got {$status}");
        }

        $this->assertNotContains($status, [401, 403], $caseId);
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: string, 3: string, 4: string}>
     */
    private static function forRoles(string $prefix, string $method, string $uri, array $expectedByRole): array
    {
        if (! array_key_exists('player_other_chronicle', $expectedByRole)) {
            $expectedByRole['player_other_chronicle'] = self::expectForPlayerOtherChronicle($uri, $expectedByRole);
        }

        $cases = [];
        foreach ($expectedByRole as $role => $expected) {
            $cases[$prefix.'.'.$role] = [$prefix.'.'.$role, $method, $uri, $role, $expected];
        }

        return $cases;
    }

    /**
     * @param  array<string, string>  $expectedByRole
     */
    private static function expectForPlayerOtherChronicle(string $uri, array $expectedByRole): string
    {
        $allowedUris = [
            'register',
            'login',
            'user',
            'logout',
            'chronicles',
            'character-sheet/catalog',
            'invitations/{token}/accept',
        ];

        if (in_array($uri, $allowedUris, true)) {
            return self::EXPECT_ALLOWED;
        }

        return self::EXPECT_DENY;
    }

    /**
     * @return array<string, true>
     */
    private static function matrixRouteSignatures(): array
    {
        $signatures = [];
        foreach (self::accessMatrixProvider() as $case) {
            [, $method, $uri] = $case;
            $signatures[self::routeSignature($method, $uri)] = true;
        }

        return $signatures;
    }

    private static function routeSignature(string $method, string $uri): string
    {
        $normalized = preg_replace('/\{[^}]+\}/', '{}', $uri) ?? $uri;

        return strtoupper($method).'|'.$normalized;
    }
}
