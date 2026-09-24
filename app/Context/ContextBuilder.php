<?php

namespace App\Context;

class ContextBuilder
{
    public const VERSION = ContextAssembler::VERSION;

    public const PROMPT_VERSION = ContextAssembler::PROMPT_VERSION;

    public function __construct(private ContextAssembler $assembler) {}

    public function build(
        string $npcName,
        string $prompt,
        int $sceneId,
        int $draftCount,
        ?int $storytellerId = null,
        ?int $gameSessionId = null,
        ?int $characterId = null,
    ): ContextBuild {
        return $this->buildReply(
            $npcName,
            $prompt,
            $sceneId,
            $draftCount,
            $storytellerId,
            $gameSessionId,
            $characterId,
        );
    }

    public function buildTopics(
        string $npcName,
        string $prompt,
        int $sceneId,
        int $draftCount,
        ?int $storytellerId = null,
        ?int $gameSessionId = null,
        ?int $characterId = null,
    ): ContextBuild {
        return $this->assembler->assemble(new ContextRequest(
            $npcName,
            $prompt,
            $sceneId,
            $draftCount,
            $storytellerId,
            $gameSessionId,
            $characterId,
            ContextPass::Topics,
            [],
            (int) config('copilot.topic_history_limit', 8),
            null,
            null,
            true,
        ));
    }

    /**
     * @param  list<string>  $searchTopics
     */
    public function buildReply(
        string $npcName,
        string $prompt,
        int $sceneId,
        int $draftCount,
        ?int $storytellerId = null,
        ?int $gameSessionId = null,
        ?int $characterId = null,
        array $searchTopics = [],
    ): ContextBuild {
        return $this->assembler->assemble(new ContextRequest(
            $npcName,
            $prompt,
            $sceneId,
            $draftCount,
            $storytellerId,
            $gameSessionId,
            $characterId,
            ContextPass::Reply,
            $searchTopics,
        ));
    }
}
