<?php

namespace Tests\Feature;

use App\Character\CharacterBiographyService;
use App\Character\CharacterBioIndexer;
use App\Character\CharacterBioSearcher;
use App\Character\CharacterLoreKnowledgeService;
use App\Character\CharacterRuleKnowledgeService;
use App\Enums\CharacterKnowledgeLevel;
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

    public function test_npc_does_not_see_storyteller_only_or_public_lore_without_a_grant(): void
    {
        $chronicle = Chronicle::factory()->create();
        $character = $this->npcIn($chronicle);
        $lore = $this->app->make(LoreEntryService::class);
        $indexer = $this->app->make(LoreIndexer::class);

        $secret = $lore->publish($chronicle, [
            'title' => 'Тайна сира',
            'canonical_text' => 'Сир Виктории служит Шабашу.',
            'status' => LoreEntryStatus::Approved,
            'visibility' => LoreVisibility::StorytellerOnly,
        ], 'v1');
        $public = $lore->publish($chronicle, [
            'title' => 'Маскарад',
            'canonical_text' => 'Каиниты не раскрывают свою природу смертным.',
            'status' => LoreEntryStatus::Approved,
            'visibility' => LoreVisibility::Public,
        ], 'v1');
        $indexer->rebuildApproved($secret);
        $indexer->rebuildApproved($public);

        $searcher = $this->app->make(LoreSearcher::class);
        $this->assertTrue($searcher->searchForCharacter($character, 'Сир Виктории служит Шабашу.')->isEmpty());
        $this->assertTrue($searcher->searchForCharacter($character, 'Каиниты не раскрывают свою природу смертным.')->isEmpty());

        $storytellerHits = $searcher->search($chronicle->id, 'Сир Виктории служит Шабашу.', includeStorytellerOnly: true);
        $this->assertNotEmpty($storytellerHits);
    }

    public function test_grant_makes_storyteller_only_lore_searchable_for_that_npc(): void
    {
        $chronicle = Chronicle::factory()->create();
        $character = $this->npcIn($chronicle);
        $other = $this->npcIn($chronicle);
        $entry = $this->app->make(LoreEntryService::class)->publish($chronicle, [
            'title' => 'Тайна сира',
            'canonical_text' => 'Сир Виктории служит Шабашу.',
            'status' => LoreEntryStatus::Approved,
            'visibility' => LoreVisibility::StorytellerOnly,
        ], 'v1');
        $this->app->make(LoreIndexer::class)->rebuildApproved($entry);
        $this->app->make(CharacterLoreKnowledgeService::class)->grant(
            $character,
            $entry,
            CharacterKnowledgeLevel::Known,
            4,
        );

        $searcher = $this->app->make(LoreSearcher::class);
        $known = $searcher->searchForCharacter($character, 'Сир Виктории служит Шабашу.');
        $leaked = $searcher->searchForCharacter($other, 'Сир Виктории служит Шабашу.');

        $this->assertNotEmpty($known);
        $this->assertTrue($known->every(fn (LoreChunk $chunk): bool => (int) $chunk->lore_entry_id === (int) $entry->id));
        $this->assertTrue($leaked->isEmpty());
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
