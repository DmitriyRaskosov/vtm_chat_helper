<?php

return [
    'history_limit' => (int) env('COPILOT_HISTORY_LIMIT', 15),
    'topic_history_limit' => (int) env('COPILOT_TOPIC_HISTORY_LIMIT', 8),
    'topic_max_output_tokens' => (int) env('COPILOT_TOPIC_MAX_OUTPUT_TOKENS', 384),
    'topic_temperature' => (float) env('COPILOT_TOPIC_TEMPERATURE', 0.1),
    'topic_max_count' => 8,
    'draft_count' => (int) env('COPILOT_DRAFT_COUNT', 3),
];
