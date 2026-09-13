<?php

namespace Tests\Feature;

use App\Character\CharacterRelationshipService;
use App\Enums\WorldEntityType;
use App\Models\Character;
use App\Models\CharacterRelationship;
use App\Models\Chronicle;
use App\Models\WorldRelation;
use App\Models\WorldRelationType;
use App\World\WorldEntityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use Tests\TestCase;

class CharacterRelationshipTest extends TestCase
{
    use RefreshDatabase;

    public function test_metrics_and_change_journal_are_not_created(): void
    {
        $this->assertTrue(Schema::hasTable('character_relationships'));
        $this->assertFalse(Schema::hasTable('relationship_changes'));
        $this->assertFalse(Schema::hasColumn('character_relationships', 'like'));
        $this->assertFalse(Schema::hasColumn('character_relationships', 'trust'));
        $this->assertFalse(Schema::hasColumn('character_relationships', 'fear'));
        $this->assertFalse(Schema::hasColumn('character_relationships', 'debt'));
        $this->assertFalse(Schema::hasColumn('character_relationships', 'intimacy'));
        $this->assertFalse(Schema::hasColumn('character_relationships', 'respect'));
        $this->assertFalse(Schema::hasColumn('character_relationships', 'note'));
        $this->assertFalse(Schema::hasColumn('character_relationships', 'revision'));
    }

    public function test_directed_relationship_a_to_b_is_not_b_to_a(): void
    {
        [$victoria, $marcus] = $this->pair();
        $service = $this->app->make(CharacterRelationshipService::class);
        $knows = WorldRelationType::query()->where('key', 'knows')->firstOrFail();

        $forward = $service->connect($victoria, $marcus, $knows);

        $this->assertSame($victoria->id, $forward->source_character_id);
        $this->assertSame($marcus->id, $forward->target_character_id);
        $this->assertDatabaseCount('character_relationships', 1);
        $this->assertDatabaseCount('world_relations', 1);
        $this->assertDatabaseMissing('character_relationships', [
            'source_character_id' => $marcus->id,
            'target_character_id' => $victoria->id,
        ]);

        $reverse = $service->connect($marcus, $victoria, $knows);

        $this->assertNotSame($forward->id, $reverse->id);
        $this->assertDatabaseCount('character_relationships', 2);
        $this->assertSame($marcus->id, $reverse->source_character_id);
        $this->assertSame($victoria->id, $reverse->target_character_id);
    }

    public function test_relationship_extension_matches_graph_endpoints(): void
    {
        [$victoria, $marcus] = $this->pair();
        $hostile = WorldRelationType::query()->where('key', 'hostile_to')->firstOrFail();

        $relationship = $this->app->make(CharacterRelationshipService::class)
            ->connect($victoria, $marcus, $hostile, note: 'Двор помнит обиду.');

        $edge = WorldRelation::query()->findOrFail($relationship->id);
        $this->assertSame($victoria->id, $edge->source_entity_id);
        $this->assertSame($marcus->id, $edge->target_entity_id);
        $this->assertSame('Двор помнит обиду.', $edge->note);
        $this->assertTrue($edge->characterRelationship->is($relationship));
    }

    public function test_self_relationship_is_rejected(): void
    {
        $character = Character::factory()->create();
        $knows = WorldRelationType::query()->where('key', 'knows')->firstOrFail();

        $this->expectException(InvalidArgumentException::class);
        $this->app->make(CharacterRelationshipService::class)
            ->connect($character, $character, $knows);
    }

    public function test_factory_creates_a_directed_pair(): void
    {
        $relationship = CharacterRelationship::factory()->create();

        $this->assertNotSame($relationship->source_character_id, $relationship->target_character_id);
        $this->assertSame(
            $relationship->sourceCharacter->chronicle_id,
            $relationship->targetCharacter->chronicle_id,
        );
    }

    /**
     * @return array{0: Character, 1: Character}
     */
    private function pair(): array
    {
        $chronicle = Chronicle::factory()->create();
        $service = $this->app->make(WorldEntityService::class);
        $victoria = $service->create($chronicle, WorldEntityType::Character, 'Виктория');
        $marcus = $service->create($chronicle, WorldEntityType::Character, 'Маркус');

        return [
            Character::query()->findOrFail($victoria->id),
            Character::query()->findOrFail($marcus->id),
        ];
    }
}
