<?php

return [
    'token_estimator' => [
        'characters_per_token' => (int) env('CONTEXT_CHARACTERS_PER_TOKEN', 3),
    ],

    'copilot' => [
        'max_input_tokens' => (int) env('CONTEXT_COPILOT_MAX_INPUT_TOKENS', 16000),
    ],

    'topic' => [
        'max_input_tokens' => (int) env('CONTEXT_TOPIC_MAX_INPUT_TOKENS', 8000),
    ],

    'assembler' => [
        'sections' => [
            'system' => [
                'max' => 1000,
            ],
            'storyteller_prompt' => [
                'max' => 1500,
            ],
            'npc_identity' => [
                'max' => 400,
            ],
            'scene' => [
                'max' => 400,
            ],
            'recent_messages' => [
                'max' => 4000,
            ],
            'diary' => [
                'max' => 1200,
            ],
            'direct_relations' => [
                'max' => 400,
            ],
            'biography' => [
                'max' => 2000,
            ],
            'personality' => [
                'max' => 1800,
            ],
            'world_lore' => [
                'max' => 700,
            ],
            'closing' => [
                'max' => 80,
            ],
        ],
    ],
];
