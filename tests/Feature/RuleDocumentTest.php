<?php

namespace Tests\Feature;

use App\Enums\CharacterStatCategory;
use App\Enums\CharacterStatusEffectType;
use App\Enums\RuleDocumentStatus;
use App\Enums\RulesetStatus;
use App\Models\Chronicle;
use App\Models\Discipline;
use App\Models\DisciplinePower;
use App\Models\RuleDocument;
use App\Models\RuleDocumentVersion;
use App\Models\User;
use App\Rulebook\CannotDeleteRuleDocumentException;
use App\Rulebook\RuleDocumentService;
use App\Rulebook\RulesetService;
use App\Rulebook\RuleVersionImmutableException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Tests\TestCase;

class RuleDocumentTest extends TestCase
{
    use RefreshDatabase;

    public function test_ruleset_edition_and_override_are_distinct_from_base_document(): void
    {
        $v20 = $this->app->make(RulesetService::class)->create('Vampire: The Masquerade', 'v20', 'ru');
        $v5 = $this->app->make(RulesetService::class)->create('Vampire: The Masquerade', 'v5', 'ru');
        $this->assertNotSame($v20->id, $v5->id);
        $this->assertSame(RulesetStatus::Published, $v20->status);

        $author = User::factory()->storyteller()->create();
        $documents = $this->app->make(RuleDocumentService::class);
        $document = $documents->publish($v20, [
            'title' => 'Dominate',
            'section' => 'disciplines.dominate',
            'canonical_text' => 'Доминирование подчиняет волю жертвы.',
            'source_reference' => 'V20 p.152',
            'status' => RuleDocumentStatus::Approved,
        ], 'Канон v20.', author: $author);

        $this->assertSame(1, $document->current_version);
        $this->assertDatabaseCount('rule_document_versions', 1);

        $chronicle = Chronicle::factory()->create();
        $override = $documents->override(
            $chronicle,
            $document,
            'В Праге Dominate не действует на гулей князя.',
            'House rule.',
            RuleDocumentStatus::Approved,
            $author,
        );

        $this->assertSame('Доминирование подчиняет волю жертвы.', $document->fresh()->canonical_text);
        $this->assertSame('В Праге Dominate не действует на гулей князя.', $override->override_text);
        $this->assertSame($chronicle->id, $override->chronicle_id);
        $this->assertSame(1, $override->current_version);
    }

    public function test_second_publish_keeps_immutable_snapshot(): void
    {
        $ruleset = $this->app->make(RulesetService::class)->create('VtM', 'v20', 'ru');
        $documents = $this->app->make(RuleDocumentService::class);
        $document = $documents->publish($ruleset, [
            'title' => 'Dominate',
            'section' => 'disciplines.dominate',
            'canonical_text' => 'Черновик.',
        ], 'v1');

        $document = $documents->publish($ruleset, [
            'canonical_text' => 'Уточнённый канон.',
        ], 'v2', $document);

        $this->assertSame(2, $document->current_version);
        $this->assertSame('Черновик.', RuleDocumentVersion::query()->where('version', 1)->value('canonical_text'));
        $this->assertSame('Уточнённый канон.', RuleDocumentVersion::query()->where('version', 2)->value('canonical_text'));
    }

    public function test_version_row_cannot_be_mutated(): void
    {
        $ruleset = $this->app->make(RulesetService::class)->create('VtM', 'v20', 'ru');
        $this->app->make(RuleDocumentService::class)->publish($ruleset, [
            'title' => 'Dominate',
            'section' => 'disciplines.dominate',
            'canonical_text' => 'Канон.',
        ], 'v1');
        $version = RuleDocumentVersion::query()->firstOrFail();

        try {
            $version->update(['canonical_text' => 'Переписано.']);
            $this->fail('Expected RuleVersionImmutableException.');
        } catch (RuleVersionImmutableException) {
        }

        $this->expectException(QueryException::class);
        DB::table('rule_document_versions')->where('id', $version->id)->update(['canonical_text' => 'SQL']);
    }

    public function test_document_links_disciplines_powers_stats_and_effects(): void
    {
        $ruleset = $this->app->make(RulesetService::class)->create('VtM', 'v20', 'ru');
        $documents = $this->app->make(RuleDocumentService::class);
        $document = $documents->publish($ruleset, [
            'title' => 'Dominate',
            'section' => 'disciplines.dominate',
            'canonical_text' => 'Доминирование подчиняет волю жертвы.',
        ], 'v1');
        $discipline = Discipline::query()->where('ruleset', 'v20')->where('key', 'dominate')->firstOrFail();
        $power = DisciplinePower::factory()->create([
            'discipline_id' => $discipline->id,
            'key' => 'command',
            'rule_key' => 'dominate.command',
        ]);

        $documents->linkDiscipline($document, $discipline);
        $documents->linkPower($document, $power);
        $documents->linkStatKey($document, CharacterStatCategory::Attribute, 'manipulation');
        $documents->linkEffectType($document, CharacterStatusEffectType::Temporary);

        $this->assertTrue($document->disciplines()->whereKey($discipline->id)->exists());
        $this->assertTrue($document->powers()->whereKey($power->id)->exists());
        $this->assertDatabaseHas('rule_document_stat_keys', [
            'rule_document_id' => $document->id,
            'stat_category' => 'attribute',
            'stat_key' => 'manipulation',
        ]);
        $this->assertDatabaseHas('rule_document_effect_types', [
            'rule_document_id' => $document->id,
            'effect_type' => 'temporary',
        ]);
    }

    public function test_publish_requires_reason_and_body(): void
    {
        $ruleset = $this->app->make(RulesetService::class)->create('VtM', 'v20', 'ru');
        $documents = $this->app->make(RuleDocumentService::class);

        try {
            $documents->publish($ruleset, [
                'title' => 'Dominate',
                'section' => 'disciplines.dominate',
                'canonical_text' => 'Текст.',
            ], '  ');
            $this->fail('Expected InvalidArgumentException.');
        } catch (InvalidArgumentException $e) {
            $this->assertSame('A change reason is required.', $e->getMessage());
        }

        $this->expectException(InvalidArgumentException::class);
        $documents->publish($ruleset, ['title' => 'Только заголовок.'], 'v1');
    }

    public function test_factory_creates_ruleset_and_document(): void
    {
        $document = RuleDocument::factory()->create();

        $this->assertDatabaseHas('rule_documents', [
            'id' => $document->id,
            'current_version' => 1,
        ]);
        $this->assertNotNull($document->ruleset);
    }

    public function test_rule_documents_are_archived_instead_of_deleted(): void
    {
        $ruleset = $this->app->make(RulesetService::class)->create('VtM', 'v20', 'ru');
        $documents = $this->app->make(RuleDocumentService::class);
        $document = $documents->publish($ruleset, [
            'title' => 'Dominate',
            'section' => 'disciplines.dominate',
            'canonical_text' => 'Доминирование подчиняет волю жертвы.',
            'status' => RuleDocumentStatus::Approved,
        ], 'v1');

        $archived = $documents->archive($document);
        $this->assertSame(RuleDocumentStatus::Archived, $archived->status);
        $this->assertSame('Доминирование подчиняет волю жертвы.', $archived->canonical_text);
        $this->assertDatabaseCount('rule_document_versions', 1);

        try {
            $archived->delete();
            $this->fail('Expected CannotDeleteRuleDocumentException.');
        } catch (CannotDeleteRuleDocumentException) {
        }

        $this->expectException(QueryException::class);
        DB::table('rule_documents')->where('id', $archived->id)->delete();
    }
}
