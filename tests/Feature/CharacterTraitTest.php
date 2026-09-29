<?php

namespace Tests\Feature;

use App\Models\Chronicle;
use App\Models\User;
use Database\Seeders\CanonClanSeeder;
use Database\Seeders\CanonSectSeeder;
use Tests\TestCase;

class CharacterTraitTest extends TestCase
{
    private User $storyteller;

    private Chronicle $chronicle;

    private int $characterId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CanonSectSeeder::class);
        $this->seed(CanonClanSeeder::class);

        $this->storyteller = User::factory()->storyteller()->create();
        $this->chronicle = Chronicle::query()->create([
            'title' => 'Тестовая хроника',
            'created_by' => $this->storyteller->id,
        ]);

        $create = $this->actingAs($this->storyteller, 'sanctum')
            ->postJson('/api/characters', [
                'canonical_name' => 'Иван',
                'character_type' => 'npc',
                'chronicle_id' => $this->chronicle->id,
            ]);
        $create->assertStatus(201);
        $this->characterId = (int) $create->json('character.id');
    }

    public function test_storyteller_can_update_traits(): void
    {
        $response = $this->actingAs($this->storyteller, 'sanctum')
            ->putJson("/api/characters/{$this->characterId}/traits", [
                'traits' => [
                    [
                        'key' => 'appearance',
                        'label' => 'Внешность',
                        'value' => 'Высокий, бледный',
                        'sort_order' => 0,
                    ],
                    [
                        'key' => 'voice',
                        'label' => 'Голос',
                        'value' => 'Низкий бархатный',
                        'sort_order' => 1,
                    ],
                ],
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('character_traits', [
            'character_id' => $this->characterId,
            'key' => 'appearance',
            'value' => 'Высокий, бледный',
        ]);
        $this->assertDatabaseHas('character_traits', [
            'character_id' => $this->characterId,
            'key' => 'voice',
            'value' => 'Низкий бархатный',
        ]);
    }

    public function test_traits_are_in_character_sheet(): void
    {
        $this->actingAs($this->storyteller, 'sanctum')
            ->putJson("/api/characters/{$this->characterId}/traits", [
                'traits' => [
                    [
                        'key' => 'tone',
                        'label' => 'Тон',
                        'value' => 'Мрачный',
                        'sort_order' => 0,
                    ],
                ],
            ])
            ->assertStatus(200);

        $response = $this->actingAs($this->storyteller, 'sanctum')
            ->getJson("/api/characters/{$this->characterId}");

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'character.traits');
        $response->assertJsonPath('character.traits.0.key', 'tone');
        $response->assertJsonPath('character.traits.0.label', 'Тон');
        $response->assertJsonPath('character.traits.0.value', 'Мрачный');
    }
}
