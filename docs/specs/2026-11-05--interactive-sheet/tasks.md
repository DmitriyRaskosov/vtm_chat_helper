# Tasks: Интерактивный Чарлист (Часть 1)

Status: Completed

## Этап 1: Backend (База и Миграция данных)
- [x] Создать `config/sheet_schema.php` с базовыми группами (attributes, abilities, state).
- [x] Создать миграцию `add_stats_to_characters_table` (jsonb).
- [x] Создать миграцию для переноса данных из `character_states` в `characters.stats` (или artisan-команду).
- [x] Добавить cast `stats => array` в модель `Character`.
- [ ] Удалить/отключить старую модель `CharacterState` (если она больше не используется).

## Этап 2: Backend (TDD - API)
- [x] Написать падающий тест `CharacterStatsApiTest`: игрок может обновить `stats` (JSON), storyteller может обновить, чужой игрок — нет.
- [x] Реализовать эндпоинт `PATCH /api/characters/{character}/stats` (в контроллере или отдельном методе).
- [x] Обновить `CharacterSheetReader`, чтобы он отдавал `stats` в ответе.
- [x] Добиться прохождения тестов (Green).

## Этап 3: Frontend (UI и Логика)
- [x] Создать компонент `StatsGrid.vue` (отрисовка групп и точек).
- [x] Реализовать логику кликов (пустая -> красная -> черная -> пустая).
- [x] Реализовать режим редактирования (кнопка блокировки).
- [x] Создать `useCharacterStats.js` для отправки изменений на бэкенд.
- [x] Заменить `SheetStateSection` в `CharacterSheetView.vue` на `StatsGrid.vue`.

## Этап 4: Финализация (DoD)
- [x] Прогон полного набора тестов (`php artisan test`).
- [x] Pint, PHPStan.
- [x] Сборка фронта в Docker.
- [x] Проверка миграции данных (старые blood/health корректно отображаются в новом чарлисте).
- [x] Обновить `spec.md` и `tasks.md` (Status: Completed, Post-Implementation Notes).