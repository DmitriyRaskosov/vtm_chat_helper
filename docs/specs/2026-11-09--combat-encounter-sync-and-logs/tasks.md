# Tasks: Боевая Секвенция (Этап 3)

**Status:** Completed
**Date:** 2026-11-09

## Stage 1: Миграции и Модели
- [x] Создать миграцию для добавления `combat_encounter_id` в `scene_logs`.
- [x] Создать миграцию для добавления `combat_encounter_id` в `character_diary_entries`.
- [x] Обновить `$fillable` в моделях `SceneLog` и `CharacterDiaryEntry`.

## Stage 2: Backend Синхронизация (TDD)
- [x] Написать падающий тест `CombatSyncTest::test_next_turn_updates_active_character_id`.
- [x] Реализовать обновление `active_character_id` в `CombatService::nextTurn`.
- [x] Написать падающий тест `CombatSyncTest::test_finish_clears_active_character_id`.
- [x] Реализовать сброс `active_character_id` при завершении боя.
- [x] Создать три класса событий (`CombatStartedEvent`, `CombatTurnChangedEvent`, `CombatParticipantUpdatedEvent`).
- [x] Внедрить `broadcast()` в методы `CombatService`.

## Stage 3: Логи Бросков (TDD)
- [x] Написать падающий тест `DiceRollTest::test_roll_during_combat_has_encounter_id`.
- [x] Обновить `DiceRollController` (или сервис): при активной секвенции записывать `combat_encounter_id`.

## Stage 4: Frontend (WebSockets)
- [x] Удалить `setInterval` из `useCombatTracker.js`.
- [x] Настроить подписку на `private-chronicle.{id}` через Laravel Echo.
- [x] Добавить обработчики событий для обновления стейта (ход, скрытность, старт, финиш).
- [x] В компоненте лога боя добавить переключатель/фильтр по `combat_encounter_id`.

## Stage 5: Finalization
- [x] Ручная проверка: два окна (игрок и мастер), проверить мгновенный переход хода.
- [x] Прогон полного тест-сьюта, Pint, PHPStan.
- [x] Прогон `npm run build`.
- [x] Обновить `spec.md` (Status: Completed) и `tasks.md`.