<?php

namespace App\Llm;

use App\Models\CopilotRequest;

final class CopilotDraftService
{
    public function __construct(private NpcCopilotService $copilot) {}

    public function create(
        string $npcName,
        string $prompt,
        int $sceneId,
        int $storytellerId,
        int $characterId,
    ): CopilotDraftCreated {
        $result = $this->copilot->drafts(
            $npcName,
            $prompt,
            $sceneId,
            $storytellerId,
            $characterId,
        );

        $copilotRequest = CopilotRequest::query()->create([
            'scene_id' => $sceneId,
            'storyteller_id' => $storytellerId,
            'npc_name' => $npcName,
            'character_id' => $characterId,
            'prompt' => $prompt,
            'drafts' => $result->drafts,
            'context_metadata' => $result->contextMetadata,
            'model' => $result->model,
            'prompt_version' => $result->promptVersion,
            'builder_version' => $result->builderVersion,
        ]);

        return new CopilotDraftCreated(
            (int) $copilotRequest->id,
            $result->drafts,
        );
    }
}
