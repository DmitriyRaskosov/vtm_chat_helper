# Tasks: Боевая Секвенция (Этап 4)

**Status:** Completed
**Date:** 2026-11-09

## Stage 1: Миграции и Модели
- [x] Создать миграцию `add_combat_encounter_id_to_character_diary_entries_table`.
- [x] Обновить `$fillable` в `CharacterDiaryEntry`.

## Stage 2: Запись L0 (TDD)
- [x] Написать падающий `CombatDiaryTest::test_l0_gets_combat_id`.
- [x] В `DiaryWriterService` добавить проверку активного `CombatEncounter` и передачу `combat_encounter_id` в L0.

## Stage 3: Инкрементальная суммаризация (TDD)
- [x] Написать падающий `CombatDiaryTest::test_no_l1_from_single_l0`.
- [x] Переписать `DiarySummarizerService::summarizeForNpc`:
    - Добавить фильтры `$combat` и `$characterId`.
    - Искать `lastL1` и брать L0 с `from_message_id > lastL1.to_message_id`.
    - Убрать жесткую проверку `if (existingL1)`.
- [x] Написать `CombatDiaryTest::test_scene_close_summarizes_only_new_l0` (проверка на дубли).

## Stage 4: Интеграция с Боем (TDD)
- [x] Добавить параметры `$combatEncounterId` и `$characterId` в `SummarizeSceneDiaryJob`.
- [x] Написать `CombatDiaryTest::test_combat_start_summarizes_pre_combat_l0`.
- [x] Внедрить вызов джобы в `CombatService::startEncounter`.
- [x] Написать `CombatDiaryTest::test_combat_finish_summarizes_combat_l0`.
- [x] Внедрить вызов джобы в `CombatService::finishEncounter` (с параметром боя). Убедиться, что `WriteDiaryJob` отработал до конца.
- [x] Написать `CombatDiaryTest::test_participant_leave_during_combat_summarizes_own_l0`.
- [x] Внедрить вызов джобы в `SceneParticipantService` (при выходе во время боя).

## Stage 5: Finalization
- [x] Полный прогон тестов, Pint, PHPStan.
- [x] Ручная проверка: сцена -> бой -> сцена -> закрытие. Проверить в БД, что создались 2-3 L1 без дублей.
- [x] Обновить `spec.md` (Status: Completed) и `tasks.md`.