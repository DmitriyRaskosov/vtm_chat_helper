<?php

namespace Tests\Feature;

use App\Character\CharacterLoreKnowledgeService;
use App\Character\CharacterRuleKnowledgeService;
use App\Context\ContextBuilder;
use App\Enums\CharacterHealthState;
use App\Enums\CharacterKnowledgeLevel;
use App\Enums\CharacterMemoryNodeType;
use App\Enums\CharacterStatCategory;
use App\Enums\CharacterType;
use App\Enums\LoreAccessLevel;
use App\Enums\LoreEntryStatus;
use App\Enums\LoreVisibility;
use App\Enums\RuleDocumentStatus;
use App\Enums\WorldEntityType;
use App\Lore\LoreEntryService;
use App\Lore\LoreIndexer;
use App\Memory\CharacterMemoryService;
use App\Models\CanonClan;
use App\Models\Character;
use App\Models\CharacterBiography;
use App\Models\CharacterStat;
use App\Models\CharacterStatus;
use App\Models\Message;
use App\Models\Scene;
use App\Models\User;
use App\Models\WorldEntity;
use App\Models\WorldRelationType;
use App\Rulebook\RuleDocumentService;
use App\Rulebook\RuleIndexer;
use App\Rulebook\RulesetService;
use App\World\WorldEntityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContextAssemblerTest extends TestCase
{
    use RefreshDatabase;

    public function test_npc_name_only_keeps_scene_and_history_without_character_layers(): void
    {
        $scene = Scene::query()->active()->firstOrFail();
        $storyteller = User::factory()->storyteller()->create();
        $message = Message::factory()->create([
            'user_id' => $storyteller->id,
            'scene_id' => $scene->id,
            'body' => 'Уникальная фраза о князе города.',
        ]);

        $build = $this->builder()->build(
            'Виктория',
            'Что известно о князе?',
            $scene->id,
            3,
            $storyteller->id,
            (int) $scene->game_session_id,
        );

        $user = $build->messages[1]['content'];
        $this->assertSame('context-assembler-v2', $build->metadata['builder_version']);
        $this->assertSame('npc-drafts-v7', $build->metadata['prompt_version']);
        $this->assertSame('reply', $build->metadata['pass']);
        $this->assertNull($build->metadata['character_id']);
        $this->assertSame((int) $scene->gameSession->chronicle_id, $build->metadata['chronicle_id']);
        $this->assertSame([$message->id], $build->metadata['included_raw_message_ids']);
        $this->assertTrue($build->metadata['sections']['scene']['included']);
        $this->assertTrue($build->metadata['sections']['npc_identity']['included']);
        $this->assertFalse($build->metadata['sections']['status']['included']);
        $this->assertFalse($build->metadata['sections']['direct_relations']['included']);
        $this->assertFalse($build->metadata['sections']['biography']['included']);
        $this->assertFalse($build->metadata['sections']['memory_graph']['included']);
        $this->assertFalse($build->metadata['sections']['world_lore']['included']);
        $this->assertFalse($build->metadata['sections']['rules']['included']);
        $this->assertStringContainsString('NPC: Виктория', $user);
        $this->assertStringContainsString('## Scene', $user);
        $this->assertStringContainsString('Уникальная фраза о князе города.', $user);
        $this->assertStringContainsString('## Scene speech', $user);
        $this->assertStringNotContainsString('## Personal memory', $user);
        $this->assertStringNotContainsString('## World', $user);
        $this->assertStringNotContainsString('## Rules', $user);
        $this->assertArrayHasKey('section_token_counts', $build->metadata);
        $this->assertSame(
            $build->metadata['section_token_counts']['system'],
            $build->metadata['sections']['system']['tokens'],
        );
    }

    public function test_character_layers_include_identity_status_relations_and_canonical_bio(): void
    {
        $scene = Scene::query()->active()->with('gameSession.chronicle')->firstOrFail();
        $chronicle = $scene->gameSession->chronicle;
        $entities = $this->app->make(WorldEntityService::class);
        $clan = CanonClan::query()->create([
            'slug' => 'ventrue-'.uniqid(),
            'name' => 'Вентру',
            'nickname' => 'Аристократы',
            'description' => 'test',
            'weakness' => '',
            'weakness_system' => '',
        ]);
        $npc = Character::query()->findOrFail(
            $entities->create($chronicle, WorldEntityType::Character, 'Виктория-'.uniqid(), typed: [
                'character_type' => CharacterType::Npc,
                'clan_id' => $clan->id,
                'generation' => 8,
                'nature' => 'Architect',
                'demeanor' => 'Director',
                'concept' => 'Князь Праги',
            ])->id,
        );
        CharacterStat::factory()->create([
            'character_id' => $npc->id,
            'category' => CharacterStatCategory::Attribute,
            'stat_key' => 'strength',
            'display_name' => 'Сила',
            'value' => 3,
            'maximum' => 5,
        ]);
        CharacterStatus::factory()->create([
            'character_id' => $npc->id,
            'chronicle_id' => $chronicle->id,
            'hunger' => 2,
            'health_state' => CharacterHealthState::Injured,
            'revision' => 4,
        ]);
        CharacterBiography::factory()->create([
            'character_id' => $npc->id,
            'summary' => 'Каноническое резюме неофита Камарильи.',
            'full_text' => 'Полный текст биографии не должен требовать векторный поиск.',
        ]);
        $camarilla = $entities->create($chronicle, WorldEntityType::Faction, 'Камарилья');
        $this->app->make(\App\World\WorldRelationService::class)->relate(
            \App\Models\WorldEntity::query()->findOrFail($npc->id),
            $camarilla,
            WorldRelationType::query()->where('key', 'member_of')->firstOrFail(),
            metadata: ['stance' => 'allied', 'loyalty' => 4, 'trust' => 3, 'role' => 'неофит'],
        );

        $build = $this->builder()->build(
            'Виктория',
            'Ответить на вопрос о Маскараде.',
            $scene->id,
            3,
            null,
            (int) $scene->game_session_id,
            $npc->id,
        );

        $user = $build->messages[1]['content'];
        $this->assertSame($npc->id, $build->metadata['character_id']);
        $this->assertSame(4, $build->metadata['data_versions']['status_revision']);
        $this->assertSame(1, $build->metadata['data_versions']['biography_version']);
        $this->assertTrue($build->metadata['sections']['status']['included']);
        $this->assertTrue($build->metadata['sections']['direct_relations']['included']);
        $this->assertTrue($build->metadata['sections']['biography']['included']);
        $this->assertStringContainsString('Type: npc', $user);
        $this->assertStringContainsString('Clan: Вентру', $user);
        $this->assertStringContainsString('Concept: Князь Праги', $user);
        $this->assertStringContainsString('Hunger: 2', $user);
        $this->assertStringContainsString('Health: injured', $user);
        $this->assertStringContainsString('Камарилья', $user);
        $this->assertStringContainsString('loyalty 4', $user);
        $this->assertStringContainsString('Каноническое резюме неофита Камарильи.', $user);
        $this->assertStringContainsString('[sheet]', $user);
        $this->assertStringContainsString('[canon]', $user);
        $this->assertContains($camarilla->id, $build->metadata['sections']['direct_relations']['provenance']['entity_ids']);
    }

    public function test_topic_pass_omits_canon_layers_and_uses_its_own_budget(): void
    {
        $scene = Scene::query()->active()->with('gameSession.chronicle')->firstOrFail();
        $storyteller = User::factory()->storyteller()->create();
        $npc = $this->npc($scene);
        CharacterBiography::factory()->create([
            'character_id' => $npc->id,
            'summary' => 'Каноническое резюме не должно попасть в топики.',
        ]);
        $this->app->make(CharacterMemoryService::class)->remember(
            $npc,
            'Личная память не должна попасть в топики.',
            CharacterMemoryNodeType::Event,
        );
        Message::factory()->create([
            'user_id' => $storyteller->id,
            'scene_id' => $scene->id,
            'body' => 'Речь сцены для топиков.',
        ]);

        $build = $this->builder()->buildTopics(
            'Виктория',
            'Что известно о князе?',
            $scene->id,
            3,
            $storyteller->id,
            (int) $scene->game_session_id,
            $npc->id,
        );

        $user = $build->messages[1]['content'];
        $this->assertSame('npc-topics-v1', $build->metadata['prompt_version']);
        $this->assertSame('topics', $build->metadata['pass']);
        $this->assertSame(8000, $build->metadata['input_token_budget']);
        $this->assertSame(384, $build->metadata['ollama_max_output_tokens']);
        $this->assertSame(8, $build->metadata['history_limit']);
        $this->assertLessThanOrEqual(8000, $build->metadata['input_token_estimate']);
        $this->assertFalse($build->metadata['sections']['status']['included']);
        $this->assertSame('not_in_pass', $build->metadata['sections']['biography']['provenance']['reason']);
        $this->assertSame('not_in_pass', $build->metadata['sections']['memory_graph']['provenance']['reason']);
        $this->assertSame('not_in_pass', $build->metadata['sections']['world_lore']['provenance']['reason']);
        $this->assertSame('not_in_pass', $build->metadata['sections']['rules']['provenance']['reason']);
        $this->assertSame('not_in_pass', $build->metadata['sections']['direct_relations']['provenance']['reason']);
        $this->assertStringContainsString('NPC: Виктория', $user);
        $this->assertStringContainsString('## Scene speech', $user);
        $this->assertStringContainsString('[speech] ', $user);
        $this->assertStringContainsString('Речь сцены для топиков.', $user);
        $this->assertStringContainsString('Extract search topics for Виктория.', $user);
        $this->assertStringNotContainsString('Каноническое резюме не должно попасть в топики.', $user);
        $this->assertStringNotContainsString('Личная память не должна попасть в топики.', $user);
        $this->assertStringNotContainsString('## Biography', $user);
        $this->assertStringNotContainsString('## World', $user);
        $this->assertStringNotContainsString('## Personal memory', $user);
        $this->assertStringNotContainsString('## Rules', $user);
        $this->assertEmpty($build->metadata['sections']['npc_identity']['provenance']['stat_ids']);
    }

    public function test_ungranted_lore_stays_out_of_the_prompt_until_granted(): void
    {
        $scene = Scene::query()->active()->with('gameSession.chronicle')->firstOrFail();
        $npc = $this->npc($scene);
        $secret = 'Внутренний круг служит Шабашу-'.uniqid();
        $entry = $this->app->make(LoreEntryService::class)->publish($scene->gameSession->chronicle, [
            'title' => 'Тайна Камарильи',
            'canonical_text' => $secret,
            'status' => LoreEntryStatus::Approved,
            'visibility' => LoreVisibility::Public,
            'classification' => LoreAccessLevel::L5,
        ], 'v1');
        $this->app->make(LoreEntryService::class)->attachEntity(
            $entry,
            WorldEntity::query()->findOrFail($npc->id),
        );
        $this->app->make(LoreIndexer::class)->rebuildApproved($entry);

        $before = $this->builder()->build(
            'Виктория',
            'Ответить.',
            $scene->id,
            3,
            null,
            (int) $scene->game_session_id,
            $npc->id,
        );
        $this->assertStringNotContainsString($secret, $before->messages[1]['content']);

        $this->app->make(CharacterLoreKnowledgeService::class)->grant(
            $npc,
            $entry,
            CharacterKnowledgeLevel::Known,
        );

        $after = $this->builder()->build(
            'Виктория',
            'Ответить.',
            $scene->id,
            3,
            null,
            (int) $scene->game_session_id,
            $npc->id,
        );
        $this->assertStringContainsString($secret, $after->messages[1]['content']);
        $this->assertContains(
            $entry->id,
            $after->metadata['sections']['world_lore']['provenance']['lore_entry_ids'],
        );
    }

    public function test_false_belief_memory_is_flagged_when_the_graph_fits(): void
    {
        $scene = Scene::query()->active()->with('gameSession.chronicle')->firstOrFail();
        $npc = $this->npc($scene);
        $text = 'Князь мёртв это ложь '.uniqid();
        $node = $this->app->make(CharacterMemoryService::class)->remember(
            $npc,
            $text,
            CharacterMemoryNodeType::Conclusion,
            isFalseBelief: true,
        );

        $build = $this->builder()->build(
            'Виктория',
            $text,
            $scene->id,
            3,
            null,
            (int) $scene->game_session_id,
            $npc->id,
        );

        $this->assertTrue($build->metadata['sections']['memory_graph']['included']);
        $this->assertContains($node->id, $build->metadata['sections']['memory_graph']['provenance']['node_ids']);
        $this->assertContains($node->id, $build->metadata['sections']['memory_graph']['provenance']['false_belief_ids']);
        $this->assertStringContainsString('[false belief]', $build->messages[1]['content']);
        $this->assertStringContainsString('[memory]', $build->messages[1]['content']);
        $this->assertStringContainsString($text, $build->messages[1]['content']);
    }

    public function test_granted_rules_appear_after_a_matching_prompt(): void
    {
        $scene = Scene::query()->active()->with('gameSession.chronicle')->firstOrFail();
        $npc = $this->npc($scene);
        $ruleset = $this->app->make(RulesetService::class)->create('VtM', 'v20', 'ru');
        $document = $this->app->make(RuleDocumentService::class)->publish($ruleset, [
            'title' => 'Dominate',
            'section' => 'disciplines.dominate',
            'canonical_text' => 'Доминирование подчиняет волю жертвы.',
            'source_reference' => 'V20 p.152',
            'status' => RuleDocumentStatus::Approved,
        ], 'v1');
        $this->app->make(RuleIndexer::class)->rebuildDocument($document);
        $this->app->make(CharacterRuleKnowledgeService::class)->grant(
            $npc,
            $document,
            CharacterKnowledgeLevel::Known,
        );

        $build = $this->builder()->build(
            'Виктория',
            'Доминирование подчиняет волю жертвы.',
            $scene->id,
            3,
            null,
            (int) $scene->game_session_id,
            $npc->id,
        );

        $this->assertTrue($build->metadata['sections']['rules']['included']);
        $this->assertStringContainsString('[rules] Доминирование подчиняет волю жертвы.', $build->messages[1]['content']);
        $this->assertContains(
            $document->id,
            $build->metadata['sections']['rules']['provenance']['rule_document_ids'],
        );
    }

    public function test_graph_rag_cannot_displace_newest_scene_messages(): void
    {
        config()->set('context.copilot.max_input_tokens', 500);
        $scene = Scene::query()->active()->with('gameSession.chronicle')->firstOrFail();
        $storyteller = User::factory()->storyteller()->create();
        $npc = $this->npc($scene);
        CharacterBiography::factory()->create([
            'character_id' => $npc->id,
            'summary' => str_repeat('Длинная биография вытеснения. ', 80),
            'full_text' => str_repeat('Ещё больше биографии для GraphRAG. ', 80),
        ]);
        $this->app->make(CharacterMemoryService::class)->remember(
            $npc,
            str_repeat('Личная память не должна вытеснить свежую реплику. ', 80),
            CharacterMemoryNodeType::Event,
        );
        Message::factory()->create([
            'user_id' => $storyteller->id,
            'scene_id' => $scene->id,
            'body' => str_repeat('Старое длинное сообщение. ', 100),
        ]);
        $newest = Message::factory()->create([
            'user_id' => $storyteller->id,
            'scene_id' => $scene->id,
            'body' => 'Самая свежая реплика.',
        ]);

        $build = $this->builder()->build(
            'Виктория',
            'Ответить.',
            $scene->id,
            3,
            $storyteller->id,
            (int) $scene->game_session_id,
            $npc->id,
        );

        $this->assertLessThanOrEqual(500, $build->metadata['input_token_estimate']);
        $this->assertSame([$newest->id], $build->metadata['included_raw_message_ids']);
        $this->assertSame(1, $build->metadata['excluded_raw_message_count']);
        $this->assertStringContainsString('Самая свежая реплика.', $build->messages[1]['content']);
        $this->assertTrue($build->metadata['sections']['recent_messages']['included']);
        $this->assertTrue($build->metadata['sections']['storyteller_prompt']['included']);
        $this->assertTrue($build->metadata['sections']['npc_identity']['included']);
    }

    public function test_prompt_stays_inside_the_global_token_budget(): void
    {
        $scene = Scene::query()->active()->firstOrFail();
        $build = $this->builder()->build('Виктория', 'Ответить.', $scene->id, 3);

        $this->assertLessThanOrEqual(12000, $build->metadata['input_token_estimate']);
        $this->assertSame(12000, $build->metadata['input_token_budget']);
        $this->assertArrayNotHasKey('included_summary_ids', $build->metadata);
        $this->assertArrayNotHasKey('included_rag_chunk_ids', $build->metadata);
    }

    private function builder(): ContextBuilder
    {
        return $this->app->make(ContextBuilder::class);
    }

    private function npc(Scene $scene): Character
    {
        return Character::query()->findOrFail(
            $this->app->make(WorldEntityService::class)->create(
                $scene->gameSession->chronicle,
                WorldEntityType::Character,
                'Виктория-'.uniqid(),
            )->id,
        );
    }
}
