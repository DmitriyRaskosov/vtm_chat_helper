<?php

namespace Tests\Feature;

use App\Character\CharacterBiographyService;
use App\Character\CharacterBioIndexer;
use App\Character\CharacterBioSearcher;
use App\Character\CharacterLoreKnowledgeService;
use App\Character\CharacterRuleKnowledgeService;
use App\Enums\CharacterKnowledgeLevel;
use App\Enums\LoreAccessLevel;
use App\Enums\LoreEntryStatus;
use App\Enums\LoreVisibility;
use App\Enums\RuleDocumentStatus;
use App\Enums\WorldEntityType;
use App\Lore\LoreEntryService;
use App\Lore\LoreIndexer;
use App\Lore\LoreSearcher;
use App\Models\Character;
use App\Models\CharacterLoreKnowledge;
use App\Models\Chronicle;
use App\Models\LoreChunk;
use App\Models\LoreEntry;
use App\Rulebook\RuleDocumentService;
use App\Rulebook\RuleIndexer;
use App\Rulebook\RuleSearcher;
use App\Rulebook\RulesetService;
use App\World\MixedChronicleException;
use App\World\WorldEntityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CharacterKnowledgeTest extends TestCase
{
    use RefreshDatabase;

    public function test_table_lore_is_visible_to_a_table_clearance_npc_without_a_grant(): void
    {
        $chronicle = Chronicle::factory()->create();
        $character = $this->npcIn($chronicle);
        $entry = $this->app->make(LoreEntryService::class)->publish($chronicle, [
            'title' => 'Маскарад',
            'canonical_text' => 'Каиниты не раскрывают свою природу смертным.',
            'status' => LoreEntryStatus::Approved,
            'visibility' => LoreVisibility::Public,
        ], 'v1');
        $this->app->make(LoreIndexer::class)->rebuildApproved($entry);

        $hits = $this->app->make(LoreSearcher::class)
            ->searchForCharacter($character, 'Каиниты не раскрывают свою природу смертным.');

        $this->assertNotEmpty($hits);
        $this->assertTrue($this->app->make(CharacterLoreKnowledgeService::class)->knownLoreEntryIds($character) === []);
    }

    public function test_secret_lore_is_hidden_until_granted_or_clearance_matches(): void
    {
        $chronicle = Chronicle::factory()->create();
        $character = $this->npcIn($chronicle);
        $other = $this->npcIn($chronicle);
        $lore = $this->app->make(LoreEntryService::class);
        $indexer = $this->app->make(LoreIndexer::class);

        $secret = $lore->publish($chronicle, [
            'title' => 'Тайна сира',
            'canonical_text' => 'Сир Виктории служит Шабашу.',
            'status' => LoreEntryStatus::Approved,
            'visibility' => LoreVisibility::Public,
            'classification' => LoreAccessLevel::L5,
        ], 'v1');
        $indexer->rebuildApproved($secret);

        $searcher = $this->app->make(LoreSearcher::class);
        $this->assertTrue($searcher->searchForCharacter($character, 'Сир Виктории служит Шабашу.')->isEmpty());

        $this->app->make(CharacterLoreKnowledgeService::class)->grant(
            $character,
            $secret,
            CharacterKnowledgeLevel::Known,
            4,
        );

        $known = $searcher->searchForCharacter($character, 'Сир Виктории служит Шабашу.');
        $leaked = $searcher->searchForCharacter($other, 'Сир Виктории служит Шабашу.');

        $this->assertNotEmpty($known);
        $this->assertTrue($known->every(fn (LoreChunk $chunk): bool => (int) $chunk->lore_entry_id === (int) $secret->id));
        $this->assertTrue($leaked->isEmpty());

        $other->lore_clearance_levels = [0, 1, 2, 3, 4, 5];
        $other->save();
        $this->assertNotEmpty($searcher->searchForCharacter($other->fresh(), 'Сир Виктории служит Шабашу.'));
    }

    public function test_deny_hides_table_lore_from_that_npc(): void
    {
        $chronicle = Chronicle::factory()->create();
        $character = $this->npcIn($chronicle);
        $other = $this->npcIn($chronicle);
        $entry = $this->app->make(LoreEntryService::class)->publish($chronicle, [
            'title' => 'Маскарад',
            'canonical_text' => 'Каиниты не раскрывают свою природу смертным.',
            'status' => LoreEntryStatus::Approved,
        ], 'v1');
        $this->app->make(LoreIndexer::class)->rebuildApproved($entry);
        $this->app->make(CharacterLoreKnowledgeService::class)->deny($character, $entry);

        $searcher = $this->app->make(LoreSearcher::class);
        $this->assertTrue($searcher->searchForCharacter($character, 'Каиниты не раскрывают свою природу смертным.')->isEmpty());
        $this->assertNotEmpty($searcher->searchForCharacter($other, 'Каиниты не раскрывают свою природу смертным.'));
    }

    public function test_initiated_clearance_sees_initiated_lore_without_a_grant(): void
    {
        $chronicle = Chronicle::factory()->create();
        $neophyte = $this->npcIn($chronicle);
        $initiated = $this->npcIn($chronicle);
        $initiated->lore_clearance_levels = [0, 1, 2];
        $initiated->save();

        $entry = $this->app->make(LoreEntryService::class)->publish($chronicle, [
            'title' => 'Внутренний круг',
            'canonical_text' => 'Двор Праги держит тайну Элизиума.',
            'status' => LoreEntryStatus::Approved,
            'classification' => LoreAccessLevel::L2,
        ], 'v1');
        $this->app->make(LoreIndexer::class)->rebuildApproved($entry);

        $searcher = $this->app->make(LoreSearcher::class);
        $this->assertTrue($searcher->searchForCharacter($neophyte, 'Двор Праги держит тайну Элизиума.')->isEmpty());
        $this->assertNotEmpty($searcher->searchForCharacter($initiated->fresh(), 'Двор Праги держит тайну Элизиума.'));
    }

    public function test_lore_grant_rejects_mixed_chronicle_character(): void
    {
        $prague = Chronicle::factory()->create();
        $berlin = Chronicle::factory()->create();
        $entry = $this->app->make(LoreEntryService::class)->publish($prague, [
            'title' => 'Маскарад',
            'canonical_text' => 'Канон Праги.',
            'status' => LoreEntryStatus::Approved,
        ], 'v1');

        $this->expectException(MixedChronicleException::class);
        $this->app->make(CharacterLoreKnowledgeService::class)->grant($this->npcIn($berlin), $entry);
    }

    public function test_own_biography_is_available_without_a_lore_grant(): void
    {
        $chronicle = Chronicle::factory()->create();
        $character = $this->npcIn($chronicle);
        $this->app->make(CharacterBiographyService::class)->publish($character, [
            'summary' => 'Виктория служит Камарилье в Праге.',
        ], 'v1');
        $this->app->make(CharacterBioIndexer::class)->rebuildForCharacter($character);

        $hits = $this->app->make(CharacterBioSearcher::class)
            ->search($character->id, 'Виктория служит Камарилье в Праге.');

        $this->assertNotEmpty($hits);
        $this->assertTrue($this->app->make(CharacterLoreKnowledgeService::class)->knownLoreEntryIds($character) === []);
    }

    public function test_sync_for_entry_grants_and_revokes(): void
    {
        $chronicle = Chronicle::factory()->create();
        $keeper = $this->npcIn($chronicle);
        $other = $this->npcIn($chronicle);
        $entry = $this->app->make(LoreEntryService::class)->publish($chronicle, [
            'title' => 'Маскарад',
            'canonical_text' => 'Канон.',
            'status' => LoreEntryStatus::Approved,
        ], 'v1');
        $knowledge = $this->app->make(CharacterLoreKnowledgeService::class);

        $knowledge->syncForEntry($entry, [$keeper->id, $other->id]);
        $this->assertEqualsCanonicalizing([$keeper->id, $other->id], $knowledge->knownCharacterIds($entry));

        $knowledge->syncForEntry($entry, [$keeper->id]);
        $this->assertSame([$entry->id], $knowledge->knownLoreEntryIds($keeper));
        $this->assertSame([], $knowledge->knownLoreEntryIds($other));
    }

    public function test_rule_search_for_character_requires_a_grant(): void
    {
        $chronicle = Chronicle::factory()->create();
        $character = $this->npcIn($chronicle);
        $ruleset = $this->app->make(RulesetService::class)->create('VtM', 'v20', 'ru');
        $document = $this->app->make(RuleDocumentService::class)->publish($ruleset, [
            'title' => 'Dominate',
            'section' => 'disciplines.dominate',
            'canonical_text' => 'Доминирование подчиняет волю жертвы.',
            'status' => RuleDocumentStatus::Approved,
        ], 'v1');
        $this->app->make(RuleIndexer::class)->rebuildDocument($document);

        $searcher = $this->app->make(RuleSearcher::class);
        $this->assertTrue($searcher->searchForCharacter(
            $character,
            'Доминирование подчиняет волю жертвы.',
            rulesetId: $ruleset->id,
        )->isEmpty());

        $this->app->make(CharacterRuleKnowledgeService::class)->grant($character, $document);
        $hits = $searcher->searchForCharacter(
            $character,
            'Доминирование подчиняет волю жертвы.',
            rulesetId: $ruleset->id,
        );
        $this->assertNotEmpty($hits);
    }

    public function test_level_two_lore_is_hidden_without_matching_clearance_level(): void
    {
        $chronicle = Chronicle::factory()->create();
        $character = $this->npcIn($chronicle);
        $character->lore_clearance_levels = [0, 1, 3];
        $character->save();

        $entry = $this->app->make(LoreEntryService::class)->publish($chronicle, [
            'title' => 'Средний секрет',
            'canonical_text' => 'Только для уровня 2.',
            'status' => LoreEntryStatus::Approved,
            'classification' => LoreAccessLevel::L2,
        ], 'v1');
        $this->app->make(LoreIndexer::class)->rebuildApproved($entry);

        $knowledge = $this->app->make(CharacterLoreKnowledgeService::class);
        $this->assertNotContains($entry->id, $knowledge->visibleLoreEntryIds($character->fresh()));
    }

    public function test_grant_opens_level_two_lore_for_npc_without_that_clearance_level(): void
    {
        $chronicle = Chronicle::factory()->create();
        $character = $this->npcIn($chronicle);
        $character->lore_clearance_levels = [0, 1, 3];
        $character->save();

        $entry = $this->app->make(LoreEntryService::class)->publish($chronicle, [
            'title' => 'Средний секрет',
            'canonical_text' => 'Только для уровня 2.',
            'status' => LoreEntryStatus::Approved,
            'classification' => LoreAccessLevel::L2,
        ], 'v1');
        $this->app->make(LoreIndexer::class)->rebuildApproved($entry);
        $this->app->make(CharacterLoreKnowledgeService::class)->grant($character, $entry);

        $knowledge = $this->app->make(CharacterLoreKnowledgeService::class);
        $this->assertContains($entry->id, $knowledge->visibleLoreEntryIds($character->fresh()));
    }

    public function test_situational_lore_requires_grant_even_when_clearance_level_matches(): void
    {
        $chronicle = Chronicle::factory()->create();
        $character = $this->npcIn($chronicle);
        $character->lore_clearance_levels = [0, 1, 2, 3, 4, 5];
        $character->save();

        $entry = $this->app->make(LoreEntryService::class)->publish($chronicle, [
            'title' => 'Ситуационный факт',
            'canonical_text' => 'Знают только отмеченные.',
            'status' => LoreEntryStatus::Approved,
            'classification' => LoreAccessLevel::L0,
            'situational' => true,
        ], 'v1');
        $this->app->make(LoreIndexer::class)->rebuildApproved($entry);

        $knowledge = $this->app->make(CharacterLoreKnowledgeService::class);
        $this->assertNotContains($entry->id, $knowledge->visibleLoreEntryIds($character->fresh()));

        $knowledge->grant($character, $entry);
        $this->assertContains($entry->id, $knowledge->visibleLoreEntryIds($character->fresh()));
    }

    public function test_factory_creates_lore_knowledge_in_the_same_chronicle(): void
    {
        $grant = CharacterLoreKnowledge::factory()->create();

        $this->assertSame($grant->character->chronicle_id, LoreEntry::query()->findOrFail($grant->lore_entry_id)->chronicle_id);
    }

    private function npcIn(Chronicle $chronicle): Character
    {
        return Character::query()->findOrFail(
            $this->app->make(WorldEntityService::class)
                ->create($chronicle, WorldEntityType::Character, 'Виктория-'.uniqid())
                ->id,
        );
    }
}
