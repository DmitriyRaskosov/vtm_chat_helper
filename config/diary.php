<?php

return [
    /*
     * Сколько необработанных сообщений должно накопиться,
     * чтобы записать новую запись в дневник NPC.
     * 0 = отключить автотриггер.
     */
    'write_threshold' => (int) env('DIARY_WRITE_THRESHOLD', 15),
    /*
     * Таймаут job'а записи дневника (в секундах).
     * Должен быть меньше QUEUE_RETRY_AFTER (600).
     */
    'write_timeout' => (int) env('DIARY_WRITE_TIMEOUT', 540),

    'summarize' => [
        'max_output_tokens' => 1500,
    ],
];