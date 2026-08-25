<?php

return [
    'history_limit' => (int) env('COPILOT_HISTORY_LIMIT', 30),
    'rag_limit' => (int) env('COPILOT_RAG_LIMIT', 5),
    'draft_count' => (int) env('COPILOT_DRAFT_COUNT', 3),
];
