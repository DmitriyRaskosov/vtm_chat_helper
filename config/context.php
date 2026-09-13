<?php

return [
    'token_estimator' => [
        'characters_per_token' => (int) env('CONTEXT_CHARACTERS_PER_TOKEN', 3),
    ],

    'copilot' => [
        'max_input_tokens' => (int) env('CONTEXT_COPILOT_MAX_INPUT_TOKENS', 12000),
    ],

    'assembler' => [
        'sections' => [
            'system' => [
                'min' => 1,
                'max' => 400,
                'required' => true,
                'priority' => 100,
                'truncation' => 'none',
            ],
            'npc_identity' => [
                'min' => 1,
                'max' => 250,
                'required' => true,
                'priority' => 90,
                'truncation' => 'stats_tail',
            ],
            'scene' => [
                'min' => 1,
                'max' => 200,
                'required' => true,
                'priority' => 85,
                'truncation' => 'description',
            ],
            'status' => [
                'min' => 0,
                'max' => 200,
                'required' => true,
                'priority' => 80,
                'truncation' => 'effects_tail',
            ],
            'storyteller_prompt' => [
                'min' => 1,
                'max' => 800,
                'required' => true,
                'priority' => 95,
                'truncation' => 'none',
            ],
            'recent_messages' => [
                'min' => 0,
                'max' => 10000,
                'required' => true,
                'priority' => 70,
                'truncation' => 'oldest_whole_messages',
            ],
            'direct_relations' => [
                'min' => 0,
                'max' => 400,
                'required' => false,
                'priority' => 50,
                'truncation' => 'extra_edges',
            ],
            'biography' => [
                'min' => 0,
                'max' => 500,
                'required' => false,
                'priority' => 45,
                'truncation' => 'drop_full_text',
            ],
            'memory_graph' => [
                'min' => 0,
                'max' => 600,
                'required' => false,
                'priority' => 30,
                'truncation' => 'lowest_score',
            ],
            'world_lore' => [
                'min' => 0,
                'max' => 700,
                'required' => false,
                'priority' => 25,
                'truncation' => 'lowest_score',
            ],
            'rules' => [
                'min' => 0,
                'max' => 400,
                'required' => false,
                'priority' => 20,
                'truncation' => 'lowest_score',
            ],
            'closing' => [
                'min' => 1,
                'max' => 80,
                'required' => true,
                'priority' => 96,
                'truncation' => 'none',
            ],
        ],
    ],
];
