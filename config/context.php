<?php

return [
    'token_estimator' => [
        'characters_per_token' => (int) env('CONTEXT_CHARACTERS_PER_TOKEN', 3),
    ],

    'l0' => [
        'max_tokens' => (int) env('CONTEXT_L0_MAX_TOKENS', 15000),
        'max_messages' => (int) env('CONTEXT_L0_MAX_MESSAGES', 50),
    ],
];
