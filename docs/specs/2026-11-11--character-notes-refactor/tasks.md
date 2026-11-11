# Tasks: Рефакторинг заметок (Character Notes)

**Status:** Completed
**Date:** 2026-11-11

## Stage 1: Миграции и Модели
- [x] Создать миграцию, которая удаляет старую таблицу `user_notes` и создает новую с `user_id`, `chronicle_id`, `character_id`, `body`.
- [x] Обновить модель `UserNote` (fillable, relations).

## Stage 2: Backend Логика (TDD)
- [x] Написать падающий тест `UserNoteTest::test_user_cannot_read_other_users_notes`.
- [x] Написать падающий тест `UserNoteTest::test_player_can_write_to_own_pc_only`.
- [x] Написать падающий тест `UserNoteTest::test_storyteller_can_write_to_npc`.
- [x] Написать падающий тест `UserNoteTest::test_upsert_note`.
- [x] Реализовать `UserNoteService` и `UserNoteController` (`show`, `update`).
- [x] Добавить маршруты в `routes/api.php`.

## Stage 3: Frontend
- [x] Обновить `useUserNotes.js`: методы `fetchNote(characterId)` и `saveNote(characterId, body)`.
- [x] Обновить `ChatNotesPanel.vue`: принимать `character_id`, загружать и сохранять заметки.
- [x] Обновить `ChatComposer.vue` / `ChatToolsToolbar.vue`: передавать `character_id` в окно заметок.
- [x] Добавить блок «Заметки» в `CharacterSheetView.vue` (textarea + кнопка сохранения).

## Stage 4: Finalization
- [x] Ручная проверка: игрок пишет заметку, переходит на другую сцену — заметка на месте. Заходит под другим юзером — заметки нет.
- [x] Прогон полного тест-сьюта, Pint, PHPStan.
- [x] `npm run build`.
- [x] Обновить `spec.md` (Status: Completed) и `tasks.md`.