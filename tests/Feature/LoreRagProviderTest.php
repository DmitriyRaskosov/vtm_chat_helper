<?php

namespace Tests\Feature;

use App\Context\ContextAssembly;
use App\Context\ContextRequest;
use App\Context\Providers\LoreRagProvider;
use App\Enums\WorldEntityType;
use App\Models\CanonLoreEntry;
use App\Models\CanonLoreEntryEntity;
use App\Models\Character;
use App\Models\Chronicle;
use App\Models\GameSession;
use App\Models\Scene;
use App\Models\User;
use App\Models\WorldEntity;
use App\Rag\VectorLiteral;
use App\World\WorldEntityService;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class LoreRagProviderTest extends TestCase
{
    public function test_retrieves_two_relevant_chunks_when_prompt_mentions_world_entity(): void
    {
        $embeddings = $this->fakeEmbeddings();

        [$assembly, $chronicle] = $this->assembly('Расскажи про Катаяны в Индии.');

        $katayans = app(WorldEntityService::class)->create(
            $chronicle,
            WorldEntityType::Location,
            'Катаяны',
        );

        $loreEntry = CanonLoreEntry::query()->create([
            'slug' => 'katayans_lore',
            'title' => 'Катаяны',
            'text' => 'placeholder',
            'category' => 'faction',
        ]);

        CanonLoreEntryEntity::query()->create([
            'lore_entry_id' => $loreEntry->id,
            'entity_type' => 'world_entity',
            'entity_id' => $katayans->id,
        ]);

        $chunkSpecs = [
            ['header' => 'История', 'content' => 'Катаяны — древний клан, чья кровь хранит тайны Востока.'],
            ['header' => 'География', 'content' => 'Земли Катаянов лежат на границе Индии и гималаев.'],
        ];

        foreach ($chunkSpecs as $spec) {
            $embeddingText = "## {$spec['header']}\n\n{$spec['content']}";
            $vector = $embeddings->embed($embeddingText);

            DB::table('canon_lore_chunks')->insert([
                'canon_lore_entry_id' => $loreEntry->id,
                'header' => $spec['header'],
                'content' => $spec['content'],
                'token_estimate' => mb_strlen($spec['content']),
                'embedding' => DB::raw("'".VectorLiteral::fromArray($vector)."'::vector"),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $section = app(LoreRagProvider::class)->assemble($assembly, 700);

        $this->assertTrue($section->included);
        $this->assertSame('lore_rag', $section->key);
        $this->assertLessThanOrEqual(700, $section->tokenEstimate);
        $this->assertStringContainsString('[Lore: Катаяны - История]', $section->content);
        $this->assertStringContainsString('[Lore: Катаяны - География]', $section->content);
        $this->assertStringContainsString($chunkSpecs[0]['content'], $section->content);
        $this->assertStringContainsString($chunkSpecs[1]['content'], $section->content);
        $this->assertCount(2, $section->provenance['chunk_ids'] ?? []);

        $queryEmbeds = array_values(array_filter(
            $embeddings->calls(),
            static fn (array $call): bool => ($call['type'] ?? '') === 'embed'
                && str_contains((string) ($call['text'] ?? ''), 'Катаян'),
        ));
        $this->assertNotEmpty($queryEmbeds);
    }

    public function test_omits_section_when_no_entity_mentioned(): void
    {
        $this->fakeEmbeddings();
        [$assembly] = $this->assembly('Обычный вечер в городе.');

        $section = app(LoreRagProvider::class)->assemble($assembly, 700);

        $this->assertFalse($section->included);
        $this->assertSame('no_entity_mention', $section->provenance['reason'] ?? null);
    }

    /**
     * @return array{0: ContextAssembly, 1: Chronicle}
     */
    private function assembly(string $prompt): array
    {
        $storyteller = User::factory()->create();
        $chronicle = Chronicle::factory()->create([
            'title' => 'Хроника лора RAG',
            'created_by' => $storyteller->id,
        ]);
        $gameSession = GameSession::factory()->active()->create([
            'chronicle_id' => $chronicle->id,
            'title' => 'Сессия',
            'created_by' => $storyteller->id,
        ]);
        $scene = Scene::factory()->active()->create([
            'game_session_id' => $gameSession->id,
            'position' => 1,
            'title' => 'Сцена',
        ]);
        $character = Character::factory()->forChronicle($chronicle)->npc()->create([
            'canonical_name' => 'Виктор',
        ]);
        $entity = WorldEntity::query()->findOrFail($character->id);

        $assembly = new ContextAssembly(
            new ContextRequest(
                npcName: 'Виктор',
                prompt: $prompt,
                sceneId: $scene->id,
                draftCount: 1,
                characterId: $character->id,
            ),
            $scene,
            $gameSession,
            $chronicle,
            $character,
            $entity,
        );

        return [$assembly, $chronicle];
    }
}
