<?php

namespace App\Console\Commands\Memory;

use App\Memory\MemoryExtractionService;
use App\Models\Scene;
use Illuminate\Console\Command;

class ExtractSceneMemoryCommand extends Command
{
    protected $signature = 'memory:extract {scene_id}';
    protected $description = 'Extract long-term memories for NPCs from a scene.';

    public function handle(MemoryExtractionService $service): int
    {
        $sceneId = (int) $this->argument('scene_id');
        $scene = Scene::query()->find($sceneId);

        if ($scene === null) {
            $this->error("Scene {$sceneId} not found.");
            return self::FAILURE;
        }

        $this->info("Extracting memory from scene #{$scene->id} \"{$scene->title}\"");
        $this->info("Since message id: ".($scene->last_extracted_to_message_id ?? 0));

        $result = $service->extractFromScene($scene);

        $this->info("Processed messages: {$result['processed_messages']}");
        $this->info("NPCs processed: {$result['npcs']}");
        $this->info("Memories created: {$result['created']}");

        if ($result['errors'] !== []) {
            $this->newLine();
            $this->warn('Errors:');
            foreach ($result['errors'] as $err) {
                $this->warn("  {$err}");
            }
        }

        return self::SUCCESS;
    }
}