<?php

namespace App\Scene;

use App\Llm\ChatProvider;
use App\Models\Scene;
use App\Prompt\PromptRepository;
use App\Scene\Exceptions\EventGenerationFailedException;

final class EventGeneratorService
{
    public function __construct(
        private ChatProvider $chat,
        private PromptRepository $prompts,
        private SceneContextService $sceneContext,
    ) {}

    public function generate(
        Scene $scene,
        string $actionDescription,
        int $attackSuccesses,
        bool $attackBotch,
        bool $attackWillpower,
        int $defenseSuccesses,
        bool $defenseBotch,
        bool $defenseWillpower,
    ): string {
        $environment = $this->sceneContext->current($scene)?->environment_details;
        $environment = is_string($environment) ? trim($environment) : '';
        if ($environment === '') {
            $environment = 'No details provided';
        }

        // Промпт считает флаг истинным только при литерале true, не при "1".
        $system = $this->prompts->get('copilot.event.system', [
            'action_description' => $actionDescription,
            'environment_details' => $environment,
            'attack_successes' => $attackSuccesses,
            'attack_botch' => $attackBotch ? 'true' : 'false',
            'attack_willpower' => $attackWillpower ? 'true' : 'false',
            'defense_successes' => $defenseSuccesses,
            'defense_botch' => $defenseBotch ? 'true' : 'false',
            'defense_willpower' => $defenseWillpower ? 'true' : 'false',
        ]);

        try {
            $turn = $this->chat->chatTurn(
                [
                    ['role' => 'system', 'content' => $system],
                ],
                [
                    'temperature' => (float) config('copilot.event_temperature'),
                    'max_tokens' => (int) config('copilot.event_max_output_tokens'),
                ],
            );
        } catch (\Throwable $e) {
            throw new EventGenerationFailedException($e);
        }

        $text = trim($turn->content);
        if ($text === '') {
            throw new EventGenerationFailedException;
        }

        return $text;
    }
}
