# Tasks: Вырезание рудиментов старого листа

## Этап 1: Миграция данных (Safety First)
- [ ] Сделать дамп базы данных (pg_dump).
- [ ] Написать команду `characters:migrate-traits-to-sheet`.
- [ ] Прогнать команду и проверить, что в `sheet_data` появились `traits` и `description`.

## Этап 2: Context Providers (Самое важное)
- [ ] Написать падающий тест `ContextAssemblerTest` (проверить, что traits читаются из JSON).
- [ ] Обновить `NpcIdentityProvider` (брать traits из `sheet_data`).
- [ ] Обновить `PersonalityProvider` (брать traits из `sheet_data`).
- [ ] Добиться прохождения тестов (Green).

## Этап 3: Удаление старья (Backend)
- [ ] Создать миграцию на удаление `character_traits` и колонок `description`, `inventory`.
- [ ] Удалить модель, контроллер, сервис и маршруты `CharacterTrait`.
- [ ] Убедиться, что все API-тесты зелёные.

## Этап 4: Удаление старья (Frontend)
- [ ] Удалить старые секции из `CharacterSheetView.vue`.
- [ ] Физически удалить файлы `SheetHeaderSection.vue`, `SheetBiographySection.vue`, `SheetTraitsSection.vue`, `SheetPlaceSection.vue`, `SheetMeritsSection.vue`, `SheetOtherSection.vue`.
- [ ] Удалить старые composables (`useCharacterTraits`, `useCharacterBiography`).
- [ ] Проверить, что лист персонажа работает и не падает.

## Этап 5: Финализация (DoD)
- [ ] Полный прогон тестов, Pint, PHPStan.
- [ ] Сборка фронта.
- [ ] Обновить `spec.md` и `tasks.md` (Status: Completed).