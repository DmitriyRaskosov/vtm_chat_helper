<?php

namespace Tests\Feature;

use App\Character\CharacterStatusRevisionException;
use App\Character\CharacterStatusService;
use App\Enums\CharacterHealthState;
use App\Enums\CharacterStatusEffectType;
use App\Enums\WorldEntityType;
use App\Models\Character;
use App\Models\CharacterStatusChange;
use App\Models\CharacterStatusEffect;
use App\Models\Chronicle;
use App\Models\Scene;
use App\Models\User;
use App\World\MixedChronicleException;
use App\World\WorldEntityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CharacterStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_status_update_and_journal_share_a_transaction_with_revision(): void
    {
        $character = Character::factory()->create();
        $storyteller = User::factory()->storyteller()->create();
        $scene = Scene::query()->active()->firstOrFail();
        $service = $this->app->make(CharacterStatusService::class);

        $status = $service->apply(
            $character,
            [
                'hunger' => 2,
                'blood_pool' => 8,
                'health_state' => CharacterHealthState::Injured,
            ],
            expectedRevision: 0,
            reason: 'Рана на Элизиуме.',
            scene: $scene,
            changedBy: $storyteller,
            gameTime: '1993-10-31 night',
        );

        $this->assertSame(1, $status->revision);
        $this->assertSame(2, $status->hunger);
        $this->assertSame(8, $status->blood_pool);
        $this->assertSame(CharacterHealthState::Injured, $status->health_state);
        $this->assertTrue($status->is($service->current($character)));

        $this->assertDatabaseCount('character_status_changes', 3);
        $this->assertDatabaseHas('character_status_changes', [
            'character_id' => $character->id,
            'field' => 'hunger',
            'reason' => 'Рана на Элизиуме.',
            'scene_id' => $scene->id,
            'changed_by' => $storyteller->id,
            'game_time' => '1993-10-31 night',
            'revision' => 1,
        ]);

        $hunger = CharacterStatusChange::query()->where('field', 'hunger')->firstOrFail();
        $this->assertSame(0, $hunger->old_value['v']);
        $this->assertSame(2, $hunger->new_value['v']);
    }

    public function test_stale_revision_is_rejected(): void
    {
        $character = Character::factory()->create();
        $service = $this->app->make(CharacterStatusService::class);
        $service->apply($character, ['hunger' => 1], expectedRevision: 0);

        $this->expectException(CharacterStatusRevisionException::class);
        $service->apply($character, ['hunger' => 2], expectedRevision: 0);
    }

    public function test_second_apply_uses_new_revision_and_keeps_history(): void
    {
        $character = Character::factory()->create();
        $service = $this->app->make(CharacterStatusService::class);
        $service->apply($character, ['hunger' => 1], expectedRevision: 0);
        $status = $service->apply($character, ['hunger' => 3], expectedRevision: 1);

        $this->assertSame(2, $status->revision);
        $this->assertSame(3, $status->hunger);
        $this->assertSame(
            [1, 2],
            CharacterStatusChange::query()->orderBy('id')->pluck('revision')->all(),
        );
    }

    public function test_active_effects_are_queryable_and_can_be_deactivated(): void
    {
        $character = Character::factory()->create();
        $service = $this->app->make(CharacterStatusService::class);
        $wound = $service->addEffect(
            $character,
            CharacterStatusEffectType::Wound,
            'Перелом руки.',
            modifier: ['dice' => -2],
            sourceType: 'scene',
            sourceId: 1,
        );
        $service->addEffect(
            $character,
            CharacterStatusEffectType::Bonus,
            'Кровь вентру.',
            modifier: ['pool' => 'social'],
        );
        $service->deactivateEffect($wound);

        $this->assertFalse($wound->fresh()->is_active);
        $this->assertSame(
            ['bonus'],
            CharacterStatusEffect::query()
                ->where('character_id', $character->id)
                ->active()
                ->orderBy('id')
                ->get()
                ->map(fn (CharacterStatusEffect $effect) => $effect->effect_type->value)
                ->all(),
        );
    }

    public function test_current_location_must_belong_to_the_same_chronicle(): void
    {
        $character = Character::factory()->create();
        $foreign = $this->app->make(WorldEntityService::class)->create(
            Chronicle::factory()->create(),
            WorldEntityType::Location,
            'Чужой Элизиум',
        );

        $this->expectException(MixedChronicleException::class);

        $this->app->make(CharacterStatusService::class)->apply(
            $character,
            ['current_location_id' => $foreign->id],
            expectedRevision: 0,
        );
    }

    public function test_current_location_of_the_same_chronicle_is_stored(): void
    {
        $character = Character::factory()->create();
        $elisium = $this->app->make(WorldEntityService::class)->create(
            $character->chronicle,
            WorldEntityType::Location,
            'Элизиум',
        );

        $status = $this->app->make(CharacterStatusService::class)->apply(
            $character,
            ['current_location_id' => $elisium->id],
            expectedRevision: 0,
        );

        $this->assertSame($elisium->id, $status->current_location_id);
    }
}
