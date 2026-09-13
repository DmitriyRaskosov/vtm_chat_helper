<?php

namespace Tests\Feature;

use App\Character\BiographyVersionImmutableException;
use App\Character\CharacterBiographyService;
use App\Enums\CharacterBiographyStatus;
use App\Models\Character;
use App\Models\CharacterBiography;
use App\Models\CharacterBiographyVersion;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use Tests\TestCase;

class CharacterBiographyTest extends TestCase
{
    use RefreshDatabase;

    public function test_character_goals_table_is_not_created(): void
    {
        $this->assertFalse(Schema::hasTable('character_goals'));
    }

    public function test_publish_writes_current_row_and_immutable_version(): void
    {
        $character = Character::factory()->create();
        $author = User::factory()->storyteller()->create();
        $service = $this->app->make(CharacterBiographyService::class);

        $biography = $service->publish(
            $character,
            [
                'summary' => 'Виктория служит Камарилье.',
                'full_text' => 'Она хранит Маскарад и отвечает сиру.',
                'principles' => 'Не раскрывать природу смертным.',
                'motivation' => 'Удержать Прагу.',
                'fears' => 'Саботаж Маскарада.',
                'desires' => 'Признание двора.',
                'behavioral_rules' => 'Говорит спокойно, не угрожает открыто.',
                'status' => CharacterBiographyStatus::Approved,
            ],
            'Первая каноническая версия.',
            $author,
        );

        $this->assertSame(1, $biography->current_version);
        $this->assertSame(CharacterBiographyStatus::Approved, $biography->status);
        $this->assertSame($author->id, $biography->approved_by);
        $this->assertNotNull($biography->approved_at);
        $this->assertTrue($biography->is($service->current($character)));
        $this->assertDatabaseCount('character_biography_versions', 1);
        $this->assertDatabaseHas('character_biography_versions', [
            'character_id' => $character->id,
            'version' => 1,
            'summary' => 'Виктория служит Камарилье.',
            'full_text' => 'Она хранит Маскарад и отвечает сиру.',
            'change_reason' => 'Первая каноническая версия.',
            'created_by' => $author->id,
        ]);
    }

    public function test_second_publish_increments_version_and_keeps_previous_snapshot(): void
    {
        $character = Character::factory()->create();
        $service = $this->app->make(CharacterBiographyService::class);
        $service->publish($character, ['summary' => 'Черновик.'], 'v1');

        $biography = $service->publish(
            $character,
            ['summary' => 'Уточнённый канон.', 'motivation' => 'Выжить зиму.'],
            'Правка мотивации.',
        );

        $this->assertSame(2, $biography->current_version);
        $this->assertSame('Уточнённый канон.', $biography->summary);
        $this->assertSame('Выжить зиму.', $biography->motivation);
        $this->assertDatabaseCount('character_biography_versions', 2);

        $first = CharacterBiographyVersion::query()->where('version', 1)->firstOrFail();
        $this->assertSame('Черновик.', $first->summary);
        $this->assertNull($first->motivation);

        $second = CharacterBiographyVersion::query()->where('version', 2)->firstOrFail();
        $this->assertSame('Уточнённый канон.', $second->summary);
        $this->assertSame('Выжить зиму.', $second->motivation);
        $this->assertSame('Правка мотивации.', $second->change_reason);
    }

    public function test_version_row_cannot_be_updated_through_eloquent(): void
    {
        $character = Character::factory()->create();
        $this->app->make(CharacterBiographyService::class)
            ->publish($character, ['summary' => 'Канон.'], 'v1');

        $version = CharacterBiographyVersion::query()->firstOrFail();

        $this->expectException(BiographyVersionImmutableException::class);
        $version->update(['summary' => 'Переписано.']);
    }

    public function test_version_row_cannot_be_updated_through_sql(): void
    {
        $character = Character::factory()->create();
        $this->app->make(CharacterBiographyService::class)
            ->publish($character, ['summary' => 'Канон.'], 'v1');

        $version = CharacterBiographyVersion::query()->firstOrFail();

        $this->expectException(QueryException::class);
        DB::table('character_biography_versions')->where('id', $version->id)->update([
            'summary' => 'SQL rewrite.',
        ]);
    }

    public function test_approve_does_not_create_a_new_version(): void
    {
        $character = Character::factory()->create();
        $approver = User::factory()->storyteller()->create();
        $service = $this->app->make(CharacterBiographyService::class);
        $service->publish($character, ['full_text' => 'Полный текст без саммари.'], 'v1');

        $biography = $service->approve($character, $approver);

        $this->assertSame(1, $biography->current_version);
        $this->assertSame(CharacterBiographyStatus::Approved, $biography->status);
        $this->assertSame($approver->id, $biography->approved_by);
        $this->assertDatabaseCount('character_biography_versions', 1);
    }

    public function test_publish_requires_reason_and_body(): void
    {
        $character = Character::factory()->create();
        $service = $this->app->make(CharacterBiographyService::class);

        try {
            $service->publish($character, ['summary' => 'Текст.'], '   ');
            $this->fail('Expected InvalidArgumentException.');
        } catch (InvalidArgumentException $e) {
            $this->assertSame('A change reason is required.', $e->getMessage());
        }

        $this->expectException(InvalidArgumentException::class);
        $service->publish($character, ['principles' => 'Только принципы.'], 'v1');
    }

    public function test_unchanged_publish_is_rejected(): void
    {
        $character = Character::factory()->create();
        $service = $this->app->make(CharacterBiographyService::class);
        $service->publish($character, ['summary' => 'Тот же канон.'], 'v1');

        $this->expectException(InvalidArgumentException::class);
        $service->publish($character, ['summary' => 'Тот же канон.'], 'повтор');
    }

    public function test_factory_can_create_a_minimal_biography(): void
    {
        $biography = CharacterBiography::factory()->create();

        $this->assertDatabaseHas('character_biographies', [
            'character_id' => $biography->character_id,
            'current_version' => 1,
        ]);
    }
}
