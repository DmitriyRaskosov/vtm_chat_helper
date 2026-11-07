# Tasks: Игровое время (In-Game Time)

**Status:** Completed

## Этап 1: Backend (БД и Модели)
- [x] Создать миграции для добавления `current_in_game_date` (chronicles), `started_at_in_game` (scenes), `in_game_date` (diary_entries).
- [x] Обновить модели: `Chronicle`, `Scene`, `CharacterDiaryEntry` (fillable, casts).
- [x] В `SceneLifecycleService` при создании сцены копировать время хроники.

## Этап 2: Backend (TDD - API и Дневники)
- [x] Написать падающий тест: рассказчик может обновить время хроники (и сцены), игрок — нет (403).
- [x] Реализовать `PATCH /api/chronicles/{chronicle}/time` и метод в контроллере/сервисе.
- [x] Написать падающий тест: при создании L0/L1 в запись пишется `in_game_date`.
- [x] Обновить `DiaryWriterService` и `DiarySummarizerService` для записи времени.
- [x] Добиться прохождения тестов (Green).

## Этап 3: Backend (TDD - LLM Context)
- [x] Написать падающий тест `DiaryProviderTest`: проверить, что в строке `[diary — 1 month ago]` используется относительное время.
- [x] Обновить `DiaryRenderer` и `DiaryProvider`: внедрить `Carbon::diffForHumans`.
- [x] Добиться прохождения тестов (Green).

## Этап 4: Frontend (UI и Часы)
- [x] Создать компонент `InGameClock.vue` (календарь и кнопки шага).
- [x] Внедрить в шапку сцены (`LocationBanner.vue`), скрыть кнопки редактирования для игроков.
- [x] Обновить composable `useSceneSession` (или создать `useInGameTime`) для отправки запроса.
- [x] Сборка фронта в Docker (`docker compose exec frontend npm run build`).

## Этап 5: Финализация (DoD)
- [x] Полный прогон тестов, Pint, PHPStan, сборка фронта.
- [x] Обновить `spec.md` и `tasks.md` (Status: Completed, Post-Implementation Notes).