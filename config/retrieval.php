<?php

return [
    'hybrid' => [
        'per_corpus_limit' => (int) env('RETRIEVAL_PER_CORPUS_LIMIT', 5),
        'max_hits' => (int) env('RETRIEVAL_MAX_HITS', 20),
    ],
    'memory_graphrag' => [
        'max_depth' => 2,
        'min_abs_weight' => 0.2,
        'max_nodes' => 24,
        'max_edges' => 48,
        'depth_decay' => 0.6,
    ],
    'world_graphrag' => [
        'max_depth' => 2,
        'min_abs_weight' => 0.0,
        'max_nodes' => 32,
        'max_edges' => 64,
        'depth_decay' => 0.6,
    ],
    'statement_timeout_ms' => (int) env('RETRIEVAL_STATEMENT_TIMEOUT_MS', 2000),
];
