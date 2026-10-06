<?php

return [
    /*
     * Сколько необработанных сообщений должно накопиться,
     * чтобы запустить extraction памяти.
     * 0 = отключить автотриггер.
     */
    'extraction_threshold' => (int) env('MEMORY_EXTRACTION_THRESHOLD', 20),

    /*
     * Таймаут job'а extraction (в секундах).
     * Должен быть меньше QUEUE_RETRY_AFTER (у нас 600).
     */
    'extraction_timeout' => (int) env('MEMORY_EXTRACTION_TIMEOUT', 540),
];