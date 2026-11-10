# Tasks: Повествование рассказчика (Narration)

**Status:** Completed
**Date:** 2026-11-10

## Stage 1: База данных и Модели
- [x] Создать миграцию `add_is_narration_to_messages_table`.
- [x] Добавить `is_narration` в `$fillable` и `$casts` модели `Message`.

## Stage 2: Backend Логика (TDD)
- [x] Написать падающий `NarrationTest::test_storyteller_can_send_narration`.
- [x] Внедрить `is_narration` в `ChatController` и `PostMessageData`.
- [x] Написать падающий `NarrationTest::test_player_cannot_send_narration`.
- [x] Добавить проверку прав в `ChatController` (Policy).
- [x] Написать падающий `NarrationTest::test_narration_included_in_diary_l0` и `test_narration_in_copilot_context_has_no_author`.
- [x] Обновить `DiaryWriterService` и провайдеры контекста (формат `[narration] текст`).
- [x] Написать падающий `NarrationTest::test_editing_narration_marks_diary_stale`.
- [x] Убедиться, что `MessageEditService` корректно обрабатывает нарратив.

## Stage 3: Frontend
- [x] В `useChatMessages.js` добавить поддержку `is_narration`.
- [x] В `ChatComposer.vue` добавить третью вкладку («Повествование»), видимую только рассказчику.
- [x] В `ChatLog.vue` (или `ChatMessage.vue`) добавить отдельный рендер для нарратива (шрифт Lora, метка «Повествование», без аватара).

## Stage 4: Промпты Копилота
- [x] В `resources/prompts/copilot/reply.system.md` добавить раздел `NARRATION`, объясняющий, как модель должна реагировать на сообщения без автора.

## Stage 5: Finalization
- [ ] Ручная проверка: рассказчик пишет нарратив, игрок видит его в IC, Copilot генерирует ответ с учетом контекста.
- [x] Прогон полного тест-сьюта, Pint, PHPStan.
- [x] `npm run build`.
- [x] Обновить `spec.md` (Status: Completed) и `tasks.md`.