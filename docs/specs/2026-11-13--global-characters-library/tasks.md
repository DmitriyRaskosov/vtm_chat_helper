# Tasks: Глобальная библиотека персонажей

**Status:** Completed
**Date:** 2026-11-13

## Stage 1: База данных и Модели
- [x] Создать миграцию с 5 таблицами (`library_characters`, `library_character_biographies`, `library_character_disciplines`, `library_character_powers`, `library_character_items`).
- [x] Создать модели для каждой таблицы, настроить связи.

## Stage 2: Сервис (TDD)
- [x] Написать падающий тест `LibraryCharacterTest::test_save_character_to_library`.
- [x] Реализовать `LibraryCharacterService::createFromCharacter` (копирование JSON, био, предметов, дисциплин).
- [x] Написать падающий тест `LibraryCharacterTest::test_import_syncs_disciplines` и `test_library_is_independent`.
- [x] Реализовать `LibraryCharacterService::instantiateInChronicle` (создание WorldEntity, Character, био, предметов, дисциплин и генерация JSON для stats.disciplines).

## Stage 3: API и Права
- [x] Создать `LibraryCharacterPolicy`.
- [x] Создать `LibraryCharacterController` (index, store, destroy, import).
- [x] Добавить маршруты в `routes/api.php` и в `AccessMatrixTest.php`.

## Stage 4: Frontend
- [x] В `ProfileView.vue` добавить вкладки «Хроники» / «Библиотека».
- [x] Создать компонент `LibraryCharacterList.vue` (плитка с шаблонами).
- [x] В карточку персонажа добавить кнопку «В библиотеку» (вызывает `POST /library-characters`).
- [x] Создать модалку импорта: выбор хроники + кнопка «Импортировать».

## Stage 5: Finalization
- [x] Ручная проверка: сохранить персонажа с предметами в библиотеку. Импортировать в новую хронику. Проверить, что предметы и дисциплины на месте.
- [x] `php artisan test`, `pint`, `phpstan`, `npm run build`.
- [x] Обновить `spec.md` и `tasks.md` (Status: Completed).

## Hotfix
- Архив в ЛК: `GET /api/profile/characters?archived=1`, фильтр `is_active` в `PlayerProfileService`, перезагрузка списка по чекбоксу, исправлен `v-if` кнопок «Восстановить» / «Удалить».
- Импорт из библиотеки: поле «Имя в хронике», `canonical_name` в API, проверка занятости имени (включая архив), тест в `LibraryCharacterApiTest`.
- Ошибка импорта только в модалке; при закрытии модалки сброс `importError`, список шаблонов снова виден.