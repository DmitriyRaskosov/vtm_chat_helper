# Tasks: Retry при finish_reason=length

**Status:** Completed

## Этап 1: Backend (БД и Модели)
- [x] Создать миграцию `add_is_truncated_to_character_diary_entries_table`.
- [x] Добавить `is_truncated` в модель `CharacterDiaryEntry`.
- [x] Обновить `DiaryEntryRepository` для сохранения флага.

## Этап 2: Backend (TDD - Copilot Retry)
- [x] Написать падающий тест: `NpcCopilotService` при `finishReason='length'` делает повторный запрос с просьбой сократить JSON.
- [x] Написать тест: если второй раз тоже `length` — выбрасывается `LlmResponseInvalidException`.
- [x] Реализовать логику Retry в `NpcCopilotService`.
- [x] Добиться прохождения тестов (Green).

## Этап 3: Backend (TDD - Дневник Retry)
- [x] Создать трейт `RetriesLlmOutput`.
- [x] Написать тест: при `length` делается запрос на продолжение и тексты склеиваются.
- [x] Написать тест: при повторном `length` ставится флаг `is_truncated = true` и пишется лог.
- [x] Внедрить трейт в `DiaryWriterService` и `DiarySummarizerService`.
- [x] Добиться прохождения тестов (Green).

## Этап 4: Frontend (UI)
- [x] Добавить `is_truncated` в `DiaryEntryResource`.
- [x] В `DiaryView.vue` добавить бейдж `[ОБРЕЗАНО]` в шапку записи.
- [x] Сборка фронта.

## Этап 5: Финализация (DoD)
- [x] Полный прогон тестов, Pint, PHPStan.
- [x] Сборка фронта.
- [x] Обновить `spec.md` и `tasks.md` (Status: Completed).