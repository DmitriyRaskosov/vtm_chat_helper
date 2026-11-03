<?php

namespace Tests\Feature;

use App\Context\ContextAssembler;
use App\Context\ContextAssembly;
use App\Context\ContextPass;
use App\Context\ContextRequest;
use App\Context\Providers\InventoryProvider;
use App\Models\Character;
use App\Models\CharacterItem;
use App\Models\Chronicle;
use App\Models\GameSession;
use App\Models\Scene;
use App\Models\SceneParticipant;
use App\Models\User;
use App\Models\WorldEntity;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class InventoryProviderTest extends TestCase
{
    public function test_splits_items_into_equipped_and_carried(): void
    {
        [$assembly, $character] = $this->assembly();

        CharacterItem::query()->create([
            'character_id' => $character->id,
            'name' => 'трость',
            'quantity' => 1,
            'is_equipped' => true,
        ]);
        CharacterItem::query()->create([
            'character_id' => $character->id,
            'name' => 'ключ',
            'quantity' => 2,
            'is_equipped' => false,
        ]);

        $section = app(InventoryProvider::class)->assemble($assembly, 200);

        $this->assertTrue($section->included);
        $this->assertSame('inventory', $section->key);
        $this->assertSame('Your Equipped: трость x1', $section->content);
        $this->assertStringNotContainsString('ключ', $section->content);
        $this->assertStringNotContainsString('Carried', $section->content);
        $this->assertLessThanOrEqual(200, $section->tokenEstimate);
    }

    public function test_omits_section_when_inventory_is_empty(): void
    {
        [$assembly] = $this->assembly();

        $section = app(InventoryProvider::class)->assemble($assembly, 200);

        $this->assertFalse($section->included);
        $this->assertSame('', $section->content);
        $this->assertSame('empty', $section->provenance['reason'] ?? null);
    }

    public function test_omits_section_when_character_has_only_carried_items(): void
    {
        [$assembly, $character] = $this->assembly();

        CharacterItem::query()->create([
            'character_id' => $character->id,
            'name' => 'ключ',
            'quantity' => 2,
            'is_equipped' => false,
        ]);

        $section = app(InventoryProvider::class)->assemble($assembly, 200);

        $this->assertFalse($section->included);
        $this->assertStringNotContainsString('ключ', $section->content);
    }

    public function test_includes_equipped_items_of_other_current_participants(): void
    {
        [$assembly, $character, $scene, , $chronicle] = $this->assembly();

        SceneParticipant::factory()->create([
            'scene_id' => $scene->id,
            'character_id' => $character->id,
        ]);

        $daria = Character::factory()->forChronicle($chronicle)->npc()->create([
            'canonical_name' => 'Дарья',
        ]);
        SceneParticipant::factory()->create([
            'scene_id' => $scene->id,
            'character_id' => $daria->id,
        ]);
        CharacterItem::query()->create([
            'character_id' => $daria->id,
            'name' => 'шляпа',
            'quantity' => 1,
            'is_equipped' => true,
        ]);
        CharacterItem::query()->create([
            'character_id' => $daria->id,
            'name' => 'письмо',
            'quantity' => 1,
            'is_equipped' => false,
        ]);

        $gone = Character::factory()->forChronicle($chronicle)->npc()->create([
            'canonical_name' => 'Ушедший',
        ]);
        SceneParticipant::factory()->left()->create([
            'scene_id' => $scene->id,
            'character_id' => $gone->id,
        ]);
        CharacterItem::query()->create([
            'character_id' => $gone->id,
            'name' => 'плащ',
            'quantity' => 1,
            'is_equipped' => true,
        ]);

        DB::flushQueryLog();
        DB::enableQueryLog();

        $section = app(InventoryProvider::class)->assemble($assembly, 200);

        $itemQueries = collect(DB::getQueryLog())
            ->filter(fn (array $query): bool => str_contains($query['query'], 'character_items'))
            ->count();

        $this->assertLessThanOrEqual(2, $itemQueries);
        $this->assertTrue($section->included);
        $this->assertSame(
            "Your Equipped:\nOthers' Equipped:\n- Дарья: шляпа x1",
            $section->content,
        );
        $this->assertStringNotContainsString('письмо', $section->content);
        $this->assertStringNotContainsString('плащ', $section->content);
        $this->assertStringNotContainsString('Виктор:', $section->content);
    }

    public function test_drops_items_that_do_not_fit_the_token_budget(): void
    {
        [$assembly, $character] = $this->assembly();

        for ($i = 1; $i <= 40; $i++) {
            CharacterItem::query()->create([
                'character_id' => $character->id,
                'name' => 'предмет номер '.$i,
                'quantity' => 1,
                'is_equipped' => $i % 2 === 0,
            ]);
        }

        $section = app(InventoryProvider::class)->assemble($assembly, 30);

        $this->assertLessThanOrEqual(30, $section->tokenEstimate);
        if ($section->included) {
            $this->assertStringContainsString('Equipped:', $section->content);
            $this->assertStringNotContainsString('Carried', $section->content);
        }
    }

    public function test_assembler_includes_inventory_and_prompt_rule(): void
    {
        [$assembly, $character, $scene, $gameSession] = $this->assembly();

        CharacterItem::query()->create([
            'character_id' => $character->id,
            'name' => 'очки',
            'quantity' => 1,
            'is_equipped' => true,
        ]);

        $build = app(ContextAssembler::class)->assemble(new ContextRequest(
            npcName: 'Виктор',
            prompt: 'Осмотрись',
            sceneId: $scene->id,
            draftCount: 1,
            gameSessionId: $gameSession->id,
            characterId: $character->id,
            pass: ContextPass::Reply,
            maxInputTokens: 8000,
            maxOutputTokens: 100,
        ));

        $this->assertSame(200, (int) config('context.assembler.sections.inventory.max'));
        $this->assertTrue($build->metadata['sections']['inventory']['included']);
        $this->assertStringContainsString('Equipped: очки x1', $build->messages[1]['content']);
        $this->assertStringContainsString('INVENTORY RULES:', $build->messages[0]['content']);
        $this->assertStringContainsString(
            'Содержимое рюкзака никому не известно',
            $build->messages[0]['content'],
        );
        $this->assertSame($assembly->character?->id, $character->id);
    }

    /**
     * @return array{0: ContextAssembly, 1: Character, 2: Scene, 3: GameSession, 4: Chronicle}
     */
    private function assembly(): array
    {
        $storyteller = User::factory()->create();
        $chronicle = Chronicle::factory()->create([
            'title' => 'Хроника инвентаря',
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
                prompt: 'test',
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

        return [$assembly, $character, $scene, $gameSession, $chronicle];
    }
}
