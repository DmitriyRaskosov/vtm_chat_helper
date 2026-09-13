<?php

namespace Tests\Feature;

use App\Character\CharacterBiographyService;
use App\Character\CharacterBioIndexer;
use App\Enums\CharacterMemoryNodeType;
use App\Enums\LoreEntryStatus;
use App\Enums\RetrievalCorpus;
use App\Enums\WorldEntityType;
use App\Lore\LoreEntryService;
use App\Lore\LoreIndexer;
use App\Memory\CharacterMemoryService;
use App\Memory\MemoryBridgeService;
use App\Models\Character;
use App\Models\Chronicle;
use App\Models\LoreChunk;
use App\Models\Message;
use App\Models\Scene;
use App\Models\WorldEntity;
use App\Models\WorldRelationType;
use App\Rag\RagIndexer;
use App\Retrieval\Hybrid\HybridRetrievalCoordinator;
use App\Retrieval\Hybrid\RetrievalHit;
use App\Retrieval\Hybrid\RetrievalRequest;
use App\World\WorldEntityService;
use App\World\WorldRelationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HybridRetrievalTest extends TestCase
{
    use RefreshDatabase;

    public function test_coordinator_tags_corpus_reason_filters_and_token_estimate(): void
    {
        $scene = Scene::query()->active()->firstOrFail();
        $chronicle = $scene->gameSession->chronicle;
        $character = $this->npcIn($chronicle);
        $this->app->make(CharacterBiographyService::class)
            ->publish($character, ['summary' => 'Виктория служит Камарилье в Праге.'], 'v1');
        $this->app->make(CharacterBioIndexer::class)->rebuildForCharacter($character);
        $this->app->make(CharacterMemoryService::class)->remember(
            $character,
            'Виктория видела князя в опере.',
            CharacterMemoryNodeType::Event,
        );
        $message = Message::factory()->create([
            'scene_id' => $scene->id,
            'body' => 'Каинит входит в Элизиум.',
        ]);
        $this->app->make(RagIndexer::class)->indexMessage($message);
        $faction = $this->app->make(WorldEntityService::class)
            ->create($chronicle, WorldEntityType::Faction, 'Камарилья');
        $this->app->make(WorldRelationService::class)->relate(
            WorldEntity::query()->findOrFail($character->id),
            $faction,
            WorldRelationType::query()->where('key', 'member_of')->firstOrFail(),
        );

        $bundle = $this->app->make(HybridRetrievalCoordinator::class)->retrieve(new RetrievalRequest(
            chronicleId: (int) $chronicle->id,
            query: 'Виктория служит Камарилье в Праге.',
            characterId: $character->id,
            gameSessionId: (int) $scene->game_session_id,
            asNpc: true,
        ));

        $this->assertNotEmpty($bundle->hits);
        foreach ($bundle->hits as $hit) {
            $this->assertInstanceOf(RetrievalHit::class, $hit);
            $this->assertNotSame('', $hit->reason);
            $this->assertArrayHasKey('chronicle_id', $hit->filters);
            $this->assertGreaterThan(0, $hit->tokenEstimate);
            $this->assertArrayHasKey('table', $hit->provenance);
        }

        $this->assertTrue(collect($bundle->hits)->contains(
            fn (RetrievalHit $hit): bool => $hit->corpus === RetrievalCorpus::Bio,
        ));
        $this->assertTrue(collect($bundle->hits)->contains(
            fn (RetrievalHit $hit): bool => $hit->corpus === RetrievalCorpus::Relation
                && str_contains($hit->content, 'member_of'),
        ));
    }

    public function test_duplicate_lore_chunks_of_one_entry_collapse_to_a_single_hit(): void
    {
        $chronicle = Chronicle::factory()->create();
        $entry = $this->app->make(LoreEntryService::class)->publish($chronicle, [
            'title' => 'Каиниты не раскрывают свою природу смертным',
            'canonical_text' => 'Каиниты не раскрывают свою природу смертным.',
            'status' => LoreEntryStatus::Approved,
        ], 'v1');
        $this->app->make(LoreIndexer::class)->rebuildApproved($entry);
        $this->assertGreaterThanOrEqual(2, LoreChunk::query()->where('lore_entry_id', $entry->id)->count());

        $bundle = $this->app->make(HybridRetrievalCoordinator::class)->retrieve(new RetrievalRequest(
            chronicleId: (int) $chronicle->id,
            query: 'Каиниты не раскрывают свою природу смертным.',
            includeMessages: false,
            includeBio: false,
            includeMemory: false,
            includeRules: false,
            includeRelations: false,
        ));

        $loreHits = array_values(array_filter(
            $bundle->hits,
            fn (RetrievalHit $hit): bool => $hit->corpus === RetrievalCorpus::Lore,
        ));
        $this->assertCount(1, $loreHits);
        $this->assertSame($entry->id, $loreHits[0]->documentId);
    }

    public function test_raw_message_is_dropped_when_a_memory_node_already_cites_it(): void
    {
        $scene = Scene::query()->active()->firstOrFail();
        $chronicle = $scene->gameSession->chronicle;
        $character = $this->npcIn($chronicle);
        $message = Message::factory()->create([
            'scene_id' => $scene->id,
            'body' => 'Князь кивнул с балкона оперы.',
        ]);
        $this->app->make(RagIndexer::class)->indexMessage($message);
        $node = $this->app->make(CharacterMemoryService::class)->remember(
            $character,
            'Князь кивнул с балкона оперы.',
            CharacterMemoryNodeType::Event,
        );
        $this->app->make(MemoryBridgeService::class)->linkMessage($node, $message);

        $bundle = $this->app->make(HybridRetrievalCoordinator::class)->retrieve(new RetrievalRequest(
            chronicleId: (int) $chronicle->id,
            query: 'Князь кивнул с балкона оперы.',
            characterId: $character->id,
            gameSessionId: (int) $scene->game_session_id,
            includeBio: false,
            includeLore: false,
            includeRules: false,
            includeRelations: false,
        ));

        $this->assertTrue(collect($bundle->hits)->contains(
            fn (RetrievalHit $hit): bool => $hit->corpus === RetrievalCorpus::Memory
                && $hit->sourceId === (int) $node->id,
        ));
        $this->assertFalse(collect($bundle->hits)->contains(
            fn (RetrievalHit $hit): bool => $hit->corpus === RetrievalCorpus::Message
                && $hit->messageId === (int) $message->id,
        ));
    }

    public function test_npc_lore_without_a_grant_is_not_returned(): void
    {
        $chronicle = Chronicle::factory()->create();
        $character = $this->npcIn($chronicle);
        $entry = $this->app->make(LoreEntryService::class)->publish($chronicle, [
            'title' => 'Маскарад',
            'canonical_text' => 'Каиниты не раскрывают свою природу смертным.',
            'status' => LoreEntryStatus::Approved,
        ], 'v1');
        $this->app->make(LoreIndexer::class)->rebuildApproved($entry);

        $npc = $this->app->make(HybridRetrievalCoordinator::class)->retrieve(new RetrievalRequest(
            chronicleId: (int) $chronicle->id,
            query: 'Каиниты не раскрывают свою природу смертным.',
            characterId: $character->id,
            asNpc: true,
            includeMessages: false,
            includeBio: false,
            includeMemory: false,
            includeRules: false,
            includeRelations: false,
        ));
        $storyteller = $this->app->make(HybridRetrievalCoordinator::class)->retrieve(new RetrievalRequest(
            chronicleId: (int) $chronicle->id,
            query: 'Каиниты не раскрывают свою природу смертным.',
            includeMessages: false,
            includeBio: false,
            includeMemory: false,
            includeRules: false,
            includeRelations: false,
        ));

        $this->assertTrue(collect($npc->hits)->where('corpus', RetrievalCorpus::Lore)->isEmpty());
        $this->assertNotEmpty(collect($storyteller->hits)->where('corpus', RetrievalCorpus::Lore));
    }

    private function npcIn(Chronicle $chronicle, string $name = 'Виктория'): Character
    {
        return Character::query()->findOrFail(
            $this->app->make(WorldEntityService::class)
                ->create($chronicle, WorldEntityType::Character, $name.'-'.uniqid())
                ->id,
        );
    }
}
