<?php

return [
    'history_limit' => (int) env('COPILOT_HISTORY_LIMIT', 30),
    'rag_limit' => (int) env('COPILOT_RAG_LIMIT', 5),
    'draft_count' => (int) env('COPILOT_DRAFT_COUNT', 3),
    'tools' => [
        'enabled' => filter_var(env('COPILOT_TOOLS_ENABLED', true), FILTER_VALIDATE_BOOLEAN),
        'max_iterations' => (int) env('COPILOT_TOOLS_MAX_ITERATIONS', 2),
        'search_limit' => (int) env('COPILOT_TOOLS_SEARCH_LIMIT', 5),
        'range_limit' => (int) env('COPILOT_TOOLS_RANGE_LIMIT', 20),
        'max_item_characters' => (int) env('COPILOT_TOOLS_MAX_ITEM_CHARACTERS', 400),
        'max_result_tokens' => (int) env('COPILOT_TOOLS_MAX_RESULT_TOKENS', 800),
        'max_loop_tokens' => (int) env('COPILOT_TOOLS_MAX_LOOP_TOKENS', 2000),
    ],
];
