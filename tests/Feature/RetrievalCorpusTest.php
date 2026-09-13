<?php

namespace Tests\Feature;

use App\Character\CharacterBiographyService;
use App\Character\CharacterBioIndexer;
use App\Character\CharacterBioSearcher;
use App\Enums\LoreEntryStatus;
use App\Enums\RuleDocumentStatus;
use App\Lore\LoreEntryService;
use App\Lore\LoreIndexer;
use App\Lore\LoreSearcher;
use App\Models\Character;
use App\Models\Chronicle;
use App\Models\GameSession;
use App\Models\Message;
use App\Models\MessageEmbedding;
use App\Models\Scene;
use App\Models\User;
use App\Rag\MessageSearcher;
use App\Rag\RagIndexer;
use App\Rulebook\RuleDocumentService;
use App\Rulebook\RuleIndexer;
use App\Rulebook\RuleSearcher;
use App\Rulebook\RulesetService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class RetrievalCorpusTest extends TestCase
{
    use RefreshDatabase;

    public function test_message_search_reads_only_the_chronicle_scoped_corpus(): void
    {
        $user = User::factory()->create();
        $scene = Scene::query()->active()->firstOrFail();
        $chronicleId = (int) $scene->gameSession->chronicle_id;
        $message = Message::factory()->create([
            'user_id' => $user->id,
            'scene_id' => $scene->id,
            'body' => 'Каинит входит в Элизиум.',
        ]);

        $this->app->make(RagIndexer::class)->indexMessage($message);

        $hits = $this->app->make(MessageSearcher::class)->search($chronicleId, 'Каинит входит в Элизиум.');
        $otherChronicle = Chronicle::factory()->create();

        $this->assertNotEmpty($hits);
        $this->assertTrue($hits->contains(fn (MessageEmbedding $row): bool => (int) $row->message_id === (int) $message->id));
        $this->assertTrue(
            $this->app->make(MessageSearcher::class)
                ->search($otherChronicle->id, 'Каинит входит в Элизиум.')
                ->isEmpty(),
        );
    }

    public function test_indexing_a_message_uses_one_corpus_and_search_stays_in_session(): void
    {
        $user = User::factory()->create();
        $scene = Scene::query()->active()->firstOrFail();
        $local = Message::factory()->create([
            'user_id' => $user->id,
            'scene_id' => $scene->id,
            'body' => 'Секрет элизиума текущей сессии.',
        ]);
        $this->app->make(RagIndexer::class)->indexMessage($local);

        $foreignSession = GameSession::factory()->create();
        $foreignScene = Scene::factory()->create(['game_session_id' => $foreignSession->id]);
        $foreign = Message::factory()->create([
            'user_id' => $user->id,
            'scene_id' => $foreignScene->id,
            'body' => 'Секрет элизиума чужой сессии.',
        ]);
        $this->app->make(RagIndexer::class)->indexMessage($foreign);

        $this->assertDatabaseCount('message_embeddings', 2);
        $this->assertFalse(Schema::hasTable('rag_chunks'));

        $hits = $this->app->make(MessageSearcher::class)->search(
            (int) $scene->gameSession->chronicle_id,
            'секрет элизиума',
            gameSessionId: (int) $scene->game_session_id,
        );

        $this->assertTrue($hits->contains(fn (MessageEmbedding $row): bool => (int) $row->message_id === (int) $local->id));
        $this->assertFalse($hits->contains(fn (MessageEmbedding $row): bool => (int) $row->message_id === (int) $foreign->id));
    }

    public function test_bio_search_does_not_query_lore_rules_or_message_corpora(): void
    {
        $character = Character::factory()->create();
        $this->app->make(CharacterBiographyService::class)
            ->publish($character, ['summary' => 'Виктория служит Камарилье в Праге.'], 'v1');
        $this->app->make(CharacterBioIndexer::class)->rebuildForCharacter($character);

        $tables = [];
        DB::listen(function ($query) use (&$tables): void {
            if (preg_match_all('/\b(character_bio_chunks|lore_chunks|game_rule_chunks|message_embeddings|rag_chunks)\b/', $query->sql, $matches)) {
                foreach ($matches[1] as $table) {
                    $tables[] = $table;
                }
            }
        });

        $hits = $this->app->make(CharacterBioSearcher::class)
            ->search($character->id, 'Виктория служит Камарилье в Праге.');

        $this->assertNotEmpty($hits);
        $this->assertContains('character_bio_chunks', $tables);
        $this->assertNotContains('lore_chunks', $tables);
        $this->assertNotContains('game_rule_chunks', $tables);
        $this->assertNotContains('message_embeddings', $tables);
        $this->assertNotContains('rag_chunks', $tables);
    }

    public function test_lore_and_rule_searchers_do_not_cross_into_other_corpora(): void
    {
        $chronicle = Chronicle::factory()->create();
        $entry = $this->app->make(LoreEntryService::class)->publish($chronicle, [
            'title' => 'Маскарад',
            'canonical_text' => 'Каиниты не раскрывают свою природу смертным.',
            'status' => LoreEntryStatus::Approved,
        ], 'v1');
        $this->app->make(LoreIndexer::class)->rebuildApproved($entry);

        $ruleset = $this->app->make(RulesetService::class)->create('VtM', 'v20', 'ru');
        $document = $this->app->make(RuleDocumentService::class)->publish($ruleset, [
            'title' => 'Dominate',
            'section' => 'disciplines.dominate',
            'canonical_text' => 'Доминирование подчиняет волю жертвы.',
            'source_reference' => 'V20 p.152',
            'status' => RuleDocumentStatus::Approved,
        ], 'v1');
        $this->app->make(RuleIndexer::class)->rebuildDocument($document);

        $loreTables = [];
        $ruleTables = [];
        $bucket = 'lore';
        DB::listen(function ($query) use (&$loreTables, &$ruleTables, &$bucket): void {
            if (! preg_match_all('/\b(character_bio_chunks|lore_chunks|game_rule_chunks|message_embeddings)\b/', $query->sql, $matches)) {
                return;
            }

            foreach ($matches[1] as $table) {
                if ($bucket === 'lore') {
                    $loreTables[] = $table;
                } else {
                    $ruleTables[] = $table;
                }
            }
        });
        $this->app->make(LoreSearcher::class)->search($chronicle->id, 'Каиниты не раскрывают свою природу смертным.');
        $this->assertContains('lore_chunks', $loreTables);
        $this->assertNotContains('game_rule_chunks', $loreTables);
        $this->assertNotContains('character_bio_chunks', $loreTables);
        $this->assertNotContains('message_embeddings', $loreTables);

        $bucket = 'rules';
        $this->app->make(RuleSearcher::class)->search(
            'Доминирование подчиняет волю жертвы.',
            $ruleset->id,
        );
        $this->assertContains('game_rule_chunks', $ruleTables);
        $this->assertNotContains('lore_chunks', $ruleTables);
        $this->assertNotContains('character_bio_chunks', $ruleTables);
        $this->assertNotContains('message_embeddings', $ruleTables);
    }
}
