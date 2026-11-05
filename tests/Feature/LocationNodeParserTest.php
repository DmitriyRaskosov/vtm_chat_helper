<?php

namespace Tests\Feature;

use App\Enums\ChronicleRole;
use App\Models\Chronicle;
use App\Models\ChronicleLocation;
use App\Models\LocationNode;
use App\Models\User;
use App\Scene\LocationNodeParserService;
use Database\Seeders\CanonClanSeeder;
use Database\Seeders\CanonSectSeeder;
use Tests\Support\JoinsChronicle;
use Tests\TestCase;

class LocationNodeParserTest extends TestCase
{
    use JoinsChronicle;

    private ChronicleLocation $location;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CanonSectSeeder::class);
        $this->seed(CanonClanSeeder::class);

        $storyteller = User::factory()->create();
        $chronicle = Chronicle::factory()->create(['created_by' => $storyteller->id]);
        $this->joinChronicle($storyteller, $chronicle, ChronicleRole::Storyteller);

        $this->location = ChronicleLocation::query()->create([
            'chronicle_id' => $chronicle->id,
            'name' => 'Церковный двор',
        ]);
    }

    public function test_parses_environment_text_into_persisted_tree_with_embeddings(): void
    {
        $llmJson = json_encode([
            'nodes' => [
                [
                    'name' => 'Церковь',
                    'description' => 'Старое здание',
                    'children' => [
                        [
                            'name' => 'Скамейки',
                            'description' => 'Дубовые',
                            'children' => [
                                [
                                    'name' => 'Скамейка №3',
                                    'description' => '100 кг',
                                    'children' => [],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ], JSON_UNESCAPED_UNICODE);

        $chat = $this->fakeChat();
        $chat->push($llmJson);

        $embeddings = $this->fakeEmbeddings();

        $environment = 'В церкви стоят дубовые скамейки, третья очень тяжёлая.';

        app(LocationNodeParserService::class)->parse($this->location, $environment);

        $requests = $chat->requests();
        $this->assertCount(1, $requests);
        $this->assertTrue($requests[0]['options']['json_object'] ?? false);
        $this->assertStringContainsString($environment, $requests[0]['messages'][0]['content']);

        $batchCalls = array_values(array_filter(
            $embeddings->calls(),
            fn (array $call): bool => ($call['type'] ?? '') === 'embedBatch',
        ));
        $this->assertCount(1, $batchCalls);
        $this->assertCount(3, $batchCalls[0]['texts']);

        $nodes = LocationNode::query()
            ->where('chronicle_location_id', $this->location->id)
            ->orderBy('id')
            ->get();

        $this->assertCount(3, $nodes);

        $church = $nodes->firstWhere('name', 'Церковь');
        $benches = $nodes->firstWhere('name', 'Скамейки');
        $benchThree = $nodes->firstWhere('name', 'Скамейка №3');

        $this->assertNotNull($church);
        $this->assertNull($church->parent_id);
        $this->assertSame('Старое здание', $church->description);

        $this->assertNotNull($benches);
        $this->assertSame($church->id, $benches->parent_id);

        $this->assertNotNull($benchThree);
        $this->assertSame($benches->id, $benchThree->parent_id);
        $this->assertSame('100 кг', $benchThree->description);
    }
}
