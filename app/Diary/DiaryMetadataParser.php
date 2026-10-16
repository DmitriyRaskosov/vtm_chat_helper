<?php

namespace App\Diary;

use App\Enums\DiaryEmotion;

final class DiaryMetadataParser
{
    /**
     * Нужен чтобы парсить вес эмоций записей дневника.
     * Формат: "[<importance> <emotion>]\n\n<текст>"
     * Пример: "[0.850 anger]\n\nНочь первая. Пустырь..."
     *
     * При отсутствии/невалидности префикса возвращает весь текст как entry
     * с null-метаданными. Один warning в лог — чтобы отслеживать деградацию
     * модели без падения пайплайна.
     *
     * @return array{entry: string, importance: ?float, emotion: ?DiaryEmotion}
     */
    public static function parse(string $raw): array
    {
        $raw = trim($raw);
        if ($raw === '') {
            return ['entry' => '', 'importance' => null, 'emotion' => null];
        }

        // Вес: 0.85, .85, 1, 1.500 (clamp ниже). Эмоция: латиница/_, без пробелов внутри.
        // Между токенами — только пробел/таб (не перевод строки).
        $pattern = '/^\[\h*((?:\d+(?:\.\d+)?|\.\d+))\h+([a-z][a-z_]{0,31})\h*\]\h*(?:\R\h*)?/i';

        if (! preg_match($pattern, $raw, $m)) {
            return ['entry' => $raw, 'importance' => null, 'emotion' => null];
        }

        $importanceRaw = $m[1] ?? '';
        if ($importanceRaw === '' || ! is_numeric($importanceRaw)) {
            return ['entry' => $raw, 'importance' => null, 'emotion' => null];
        }

        $importance = (float) $importanceRaw;
        if (! is_finite($importance)) {
            return ['entry' => $raw, 'importance' => null, 'emotion' => null];
        }
        if ($importance < 0.0) {
            $importance = 0.0;
        } elseif ($importance > 1.0) {
            $importance = 1.0;
        }

        $emotion = DiaryEmotion::tryFromAlias($m[2]);
        if ($emotion === null) {
            return ['entry' => $raw, 'importance' => null, 'emotion' => null];
        }
        $entry = trim(substr($raw, strlen($m[0])));

        if ($entry === '') {
            return ['entry' => $raw, 'importance' => null, 'emotion' => null];
        }

        return [
            'entry' => $entry,
            'importance' => $importance,
            'emotion' => $emotion,
        ];
    }
}