<?php

namespace Tests\Feature;

use App\Enums\ChronicleRole;
use App\Models\Chronicle;
use App\Models\ChronicleLocation;
use App\Models\GameSession;
use App\Models\Scene;
use App\Models\SceneContext;
use App\Models\User;
use App\Rag\EmbeddingProvider;
use App\Scene\EventGeneratorService;
use Database\Seeders\CanonClanSeeder;
use Database\Seeders\CanonSectSeeder;
use Tests\Support\CreatesLocationNodes;
use Tests\Support\JoinsChronicle;
use Tests\TestCase;

class EventGeneratorLocationTest extends TestCase
{
    use CreatesLocationNodes;
    use JoinsChronicle;

    private Scene $scene;

    private ChronicleLocation $chronicleLocation;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CanonSectSeeder::class);
        $this->seed(CanonClanSeeder::class);

        $storyteller = User::factory()->create();
        $chronicle = Chronicle::factory()->create(['created_by' => $storyteller->id]);
        $this->joinChronicle($storyteller, $chronicle, ChronicleRole::Storyteller);

        $this->chronicleLocation = ChronicleLocation::query()->create([
            'chronicle_id' => $chronicle->id,
            'name' => 'Площадь',
            'environment_details' => 'Старое описание шаблона',
        ]);

        $gameSession = GameSession::factory()->active()->create([
            'chronicle_id' => $chronicle->id,
            'created_by' => $storyteller->id,
        ]);

        $this->scene = Scene::factory()->active()->create([
            'game_session_id' => $gameSession->id,
            'position' => 1,
        ]);

        $context = SceneContext::query()->firstOrNew(['scene_id' => $this->scene->id]);
        $context->chronicle_id = $chronicle->id;
        $context->chronicle_location_id = $this->chronicleLocation->id;
        $context->environment_details = 'Пол каменный, скамьи из дуба';
        $context->save();
    }

    public function test_event_prompt_includes_general_and_specific_location_nodes_without_duplicates(): void
    {
        $embeddings = $this->fakeEmbeddings();
        $action = 'Порижана бьет скамьей';

        $this->createLocationNode(
            $this->chronicleLocation,
            'Пол',
            'камень',
            null,
            $embeddings->embed('Пол: камень'),
        );

        $this->createLocationNode(
            $this->chronicleLocation,
            'Скамья',
            'дуб, 100кг',
            null,
            $embeddings->embed($action),
        );

        $fake = $this->fakeChat()->push('Исход боя.');

        app(EventGeneratorService::class)->generate(
            $this->scene,
            $action,
            2,
            false,
            false,
            1,
            false,
            false,
        );

        $system = $fake->requests()[0]['messages'][0]['content'];
        $this->assertIsString($system);

        $this->assertStringContainsString('[General Environment]', $system);
        $this->assertStringContainsString('- Пол: камень', $system);

        $this->assertStringContainsString('[Specific Objects]', $system);
        $this->assertStringContainsString('- Скамья: дуб, 100кг', $system);

        $this->assertSame(1, substr_count($system, '- Пол: камень'));
        $this->assertSame(1, substr_count($system, '- Скамья: дуб, 100кг'));
        $this->assertStringNotContainsString('[General Environment]'."\n".'- Скамья:', $system);
    }

    public function test_fallback_uses_scene_environment_when_template_has_no_nodes(): void
    {
        $fake = $this->fakeChat()->push('Описание.');

        app(EventGeneratorService::class)->generate(
            $this->scene,
            'Удар',
            1,
            false,
            false,
            0,
            false,
            false,
        );

        $system = $fake->requests()[0]['messages'][0]['content'];
        $this->assertIsString($system);
        $this->assertStringContainsString('Location objects:', $system);
        $this->assertStringContainsString('Пол каменный, скамьи из дуба', $system);
        $this->assertStringNotContainsString('[General Environment]', $system);
    }

    public function test_keyword_search_finds_bench_when_vector_score_is_low(): void
    {
        $stored = array_fill(0, 1024, 0.0);
        $stored[0] = 1.0;
        $query = array_fill(0, 1024, 0.0);
        $query[1] = 1.0;
        $this->bindFixedEmbedding($query);

        $hall = $this->createLocationNode($this->chronicleLocation, 'Зал', 'камень', null, $stored);
        $furniture = $this->createLocationNode($this->chronicleLocation, 'Мебель', null, $hall->id, $stored);
        $this->createLocationNode($this->chronicleLocation, 'Скамья', 'дуб', $furniture->id, $stored);

        $fake = $this->fakeChat()->push('Исход боя.');

        app(EventGeneratorService::class)->generate(
            $this->scene,
            'бьет скамьей',
            2,
            false,
            false,
            1,
            false,
            false,
        );

        $system = $fake->requests()[0]['messages'][0]['content'];
        $this->assertIsString($system);
        $this->assertStringContainsString('[Specific Objects]', $system);
        $this->assertStringContainsString('- Скамья: дуб', $system);
        $this->assertSame(1, substr_count($system, '- Скамья: дуб'));
    }

    public function test_vector_and_keyword_hit_on_the_same_node_appears_once(): void
    {
        $embeddings = $this->fakeEmbeddings();
        $action = 'бьет скамьей';
        $matching = $embeddings->embed($action);

        $hall = $this->createLocationNode(
            $this->chronicleLocation,
            'Зал',
            'камень',
            null,
            $embeddings->embed('зал'),
        );
        $furniture = $this->createLocationNode(
            $this->chronicleLocation,
            'Мебель',
            null,
            $hall->id,
            $embeddings->embed('мебель'),
        );
        $this->createLocationNode(
            $this->chronicleLocation,
            'Скамья',
            'дуб, 100кг',
            $furniture->id,
            $matching,
        );

        $fake = $this->fakeChat()->push('Исход боя.');

        app(EventGeneratorService::class)->generate(
            $this->scene,
            $action,
            2,
            false,
            false,
            1,
            false,
            false,
        );

        $system = $fake->requests()[0]['messages'][0]['content'];
        $this->assertIsString($system);
        $this->assertSame(1, substr_count($system, '- Скамья: дуб, 100кг'));
    }

    /**
     * @param  list<float>  $vector
     */
    private function bindFixedEmbedding(array $vector): void
    {
        $this->app->instance(EmbeddingProvider::class, new class($vector) implements EmbeddingProvider
        {
            /**
             * @param  list<float>  $vector
             */
            public function __construct(private array $vector) {}

            public function embed(string $text): array
            {
                return $this->vector;
            }

            public function embedBatch(array $texts): array
            {
                return array_map(fn (string $text): array => $this->vector, $texts);
            }

            public function dimensions(): int
            {
                return 1024;
            }

            public function model(): string
            {
                return 'fixed-embedding';
            }
        });
    }
}
