# Plan: Рефакторинг заметок (Character Notes)

**Status:** Completed
**Date:** 2026-11-11

## 1. Подход и архитектура

**Миграция БД.**
Так как данные тестовые, мы делаем чистую миграцию:
1. `Schema::dropIfExists('user_notes')`.
2. `Schema::create('user_notes')`:
   - `id`
   - `user_id` (FK users)
   - `chronicle_id` (FK chronicles)
   - `character_id` (FK characters)
   - `body` (text, nullable)
   - `timestamps`
   - Уникальный ключ `(user_id, character_id, chronicle_id)` для `upsert`.

**API.**
Старые эндпоинты `GET/PUT /api/user-notes` (без привязки к персонажу) удаляются.
Новые эндпоинты:
*   `GET /api/chronicles/{chronicle}/characters/{character}/note` — получить заметку.
*   `PUT /api/chronicles/{chronicle}/characters/{character}/note` — сохранить (upsert).
Контроллер `UserNoteController` обновляется. 
Policy/Валидация: 
*   Игрок может запрашивать только `character_id`, у которого `user_id === auth()->id()`.
*   Рассказчик может запрашивать любого `character_id` в рамках хроники.

**Сервис `UserNoteService`.**
Метод `getNote(User $user, Character $character, Chronicle $chronicle)` и `saveNote(...)`. Вся проверка прав (кто может писать) инкапсулируется здесь.

**Frontend.**
*   `useUserNotes.js`: обновляем API-вызовы (добавляем `character_id`).
*   `ChatNotesPanel.vue`: привязываем к текущему активному персонажу. (Если рассказчик пишет от лица NPC, заметки открываются для этого NPC).
*   `CharacterSheetView.vue`: добавляем блок «Заметки» (textarea) в интерфейс листа персонажа.
*   При переходе в чат запрос отправляется на новый эндпоинт.

## 2. Затрагиваемые файлы

**Infra / DB:**
*   `database/migrations/xxxx_recreate_user_notes_table.php`
*   `app/Models/UserNote.php` — обновить `$fillable` и связи.

**Backend:**
*   `app/Http/Controllers/UserNoteController.php` — новые методы `show`, `update`.
*   `app/Http/Requests/SaveUserNoteRequest.php` — валидация.
*   `app/Notes/UserNoteService.php` — логика прав и upsert.
*   `routes/api.php` — новые маршруты.

**Frontend:**
*   `frontend/src/composables/useUserNotes.js` — новые запросы.
*   `frontend/src/components/chat/ChatNotesPanel.vue` — использование composable.
*   `frontend/src/views/CharacterSheetView.vue` — блок заметок.
*   `frontend/src/components/chat/ChatComposer.vue` — прокидывание `character_id` для окна заметок.

**Tests:**
*   `tests/Feature/UserNoteTest.php` — проверки прав (игрок A не видит заметки игрока B), upsert, storyteller может писать NPC.

## 3. Верификация AC

| AC | Как проверяется |
|---|---|
| AC-1 | `UserNoteTest::test_migration_recreates_table` (неявно, через остальные тесты) |
| AC-2 | `UserNoteTest::test_user_cannot_read_other_users_notes` |
| AC-3 | `UserNoteTest::test_player_can_write_to_own_pc_only`, `test_storyteller_can_write_to_npc` |
| AC-4 | `UserNoteTest::test_upsert_note` |
| AC-5, AC-6 | Ручная проверка в браузере (чат и ЛК). |
| AC-7 | Проверка миграции на пустой таблице. |

## 4. Риски

*   **Риск:** Сломать чат, если `character_id` не передается.
    **Митигация:** Использовать `useChatMessages.js` для получения текущего `active_character_id` (для игрока — его PC, для рассказчика — выбранный NPC).
*   **Риск:** Случайно удалить данные пользователей.
    **Митигация:** Мы договорились, что данные тестовые, но миграция должна быть чистой (`dropIfExists` -> `create`).

## 5. Допущения

*   [Assumption] В `ChatNotesPanel.vue` заметки всегда доступны для текущего выбранного персонажа (если это игрок — его PC, если мастер — текущий NPC в селекторе).