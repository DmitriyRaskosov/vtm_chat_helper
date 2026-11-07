# Tasks: Вырезание рудиментов старого листа

**Status:** Completed

## Этап 1: Миграция данных (Safety First)
- [x] Сделать дамп базы данных (pg_dump).
- [x] Написать команду `characters:migrate-traits-to-sheet`.
- [x] Прогнать команду и проверить, что в `sheet_data` появились `traits` и `description`.

## Этап 2: Context Providers (Самое важное)
- [x] Написать падающий тест `ContextAssemblerTest` (проверить, что traits читаются из JSON).
- [x] Обновить `NpcIdentityProvider` (брать traits из `sheet_data`).
- [x] Обновить `PersonalityProvider` (брать traits из `sheet_data`).
- [x] Добиться прохождения тестов (Green).

## Этап 3: Удаление старья (Backend)
- [x] Создать миграцию на удаление `character_traits` и колонок `description`, `inventory`.
- [x] Удалить модель, контроллер, сервис и маршруты `CharacterTrait`.
- [x] Убедиться, что все API-тесты зелёные.

## Этап 4: Удаление старья (Frontend)
- [x] Удалить старые секции из `CharacterSheetView.vue`.
- [x] Физически удалить файлы `SheetHeaderSection.vue`, `SheetBiographySection.vue`, `SheetTraitsSection.vue`, `SheetPlaceSection.vue`, `SheetMeritsSection.vue`, `SheetOtherSection.vue`.
- [x] Удалить старые composables (`useCharacterTraits`, `useCharacterBiography`).
- [x] Проверить, что лист персонажа работает и не падает.

## Этап 5: Финализация (DoD)
- [x] Полный прогон тестов, Pint, PHPStan.
- [x] Сборка фронта.
- [x] Обновить `spec.md` и `tasks.md` (Status: Completed).