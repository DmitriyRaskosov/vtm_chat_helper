# Plan: Повествование рассказчика (Narration)

**Status:** Completed
**Date:** 2026-11-10

## 1. Подход и архитектура

**Схема БД.**
Добавляем колонку `is_narration` (boolean, default false) в таблицу `messages`. Валидация гарантирует, что `is_narration` не может быть true одновременно с `is_ooc` или `is_system`.

**Логика и валидация.**
В `ChatController::store` добавляется проверка Policy: отправлять `is_narration` может только рассказчик. В `PostMessageData` добавляется поле.
`MessageEditService::markDiaryStale` уже отслеживает редактирование по ID сообщения, поэтому для нарратива ничего менять не нужно — он автоматически пометит L0 как stale, если сообщение входит в его диапазон.

**Формат в контексте и дневнике.**
*   В `ContextAssembler` и `DiaryWriterService` (при формировании текста для LLM) сообщение с `is_narration` форматируется без имени автора: `[narration] Абрахам бьёт Порижану...`.
*   `Message::displayAuthor()` возвращает строку «Повествование» или пустую строку (в зависимости от контекста использования).

**Frontend.**
*   В `ChatComposer.vue` добавляется третья вкладка «Повествование» (видна только рассказчику). Переключение режима аналогично IC/OOC.
*   В `ChatLog.vue` (или `ChatMessage.vue`) добавляется ветка рендеринга для `is_narration`. Аватар не выводится. Текст выводится шрифтом `Lora` (serif) без курсива, с меткой «Повествование» и легким акцентом слева (border-left).

## 2. Затрагиваемые файлы

**Infra / DB:**
*   `database/migrations/xxxx_add_is_narration_to_messages_table.php`
*   `app/Models/Message.php` — добавить в `$fillable`, `$casts`.

**Backend:**
*   `app/Messages/PostMessageData.php` — добавить `is_narration`.
*   `app/Messages/MessageService.php` — сохранять флаг.
*   `app/Http/Controllers/ChatController.php` — валидация прав.
*   `app/Diary/DiaryWriterService.php` — форматировать как `[narration]`.
*   `app/Context/ContextAssembler.php` (или соответствующий провайдер) — форматировать как `[narration]`.

**Frontend:**
*   `frontend/src/composables/useChatMessages.js` — поддержка нового режима.
*   `frontend/src/components/chat/ChatComposer.vue` — третья вкладка.
*   `frontend/src/components/chat/ChatLog.vue` — стилизация нарратива.

**Tests:**
*   `tests/Feature/NarrationTest.php` — проверки прав, контекста, дневника.

## 3. Верификация AC

| AC | Как проверяется |
|---|---|
| AC-1, AC-2 | `NarrationTest::test_storyteller_can_send_narration`, `test_player_cannot_send_narration` |
| AC-3 | Ручная проверка UI (шрифт, отсутствие аватара). |
| AC-4 | `NarrationTest::test_narration_included_in_diary_l0` |
| AC-5 | `NarrationTest::test_narration_in_copilot_context_has_no_author` |
| AC-6 | `NarrationTest::test_editing_narration_marks_diary_stale` |

## 4. Риски

*   **Риск:** Сломать существующий парсер текста в `ChatLog.vue`.
    **Митигация:** Аккуратно добавить условие `if (message.is_narration)` перед парсингом реплик.
*   **Риск:** LLM запутается в формате `[narration]` без имени.
    **Митигация:** В системный промпт Копилота уже добавлена секция `OOC` (игнорировать). Добавим секцию `NARRATION` (воспринимать как объективную реальность, не обращаться к автору).

## 5. Допущения

*   [Assumption] Мы используем флаг `is_narration`, не меняя структуру `is_ooc` / `is_system`.
*   [Assumption] Нарратив пишется в ленту IC, не в OOC.