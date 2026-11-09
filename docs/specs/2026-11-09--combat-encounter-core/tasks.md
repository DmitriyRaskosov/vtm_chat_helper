# Tasks: Боевая Секвенция Сцены (Этап 1)

**Status:** Completed
**Date:** 2026-11-09

## Stage 1: Foundation
- [x] Создать миграцию `create_combat_encounters_table` (с `stat_keys` jsonb
      и partial unique по `(scene_id) WHERE status='active'`).
- [x] Создать миграцию `create_combat_participants_table` (с unique
      `(combat_encounter_id, character_id)`).
- [x] Создать модели `CombatEncounter` и `CombatParticipant` с кастами
      и relations.
- [x] Enum `CombatEncounterStatus`.

## Stage 2: Service (TDD)
- [x] Падающий `CombatServiceTest::test_start_creates_active_encounter`
      + `test_start_is_idempotent` + `test_start_race_safe`.
- [x] Реализовать `CombatService::startEncounter`.
- [x] Падающий `CombatServiceTest::test_add_sets_initiative_null`.
- [x] Реализовать `CombatService::addParticipant`.
- [x] Падающий `CombatServiceTest::test_initiative_sums_stats`
      + `test_initiative_counts_temporary_dots`
      + `test_initiative_adds_negative_health_penalty` (−2 → 8 → 6)
      + `test_initiative_with_empty_stats`.
- [x] Реализовать `CombatService::rollInitiative` (с AC-15: сброс
      `current_turn_index=0`, `current_round=1`).
- [x] Падающий `CombatServiceTest::test_sort_desc_by_initiative`
      + `test_sort_ties_by_first_stat` + `test_inactive_go_to_end`
      + `test_no_sort_before_first_roll`.
- [x] Реализовать `CombatService::sortByInitiative`.
- [x] Падающий `CombatServiceTest::test_next_turn_skips_inactive`
      + `test_next_turn_wraps_round_no_reroll`
      + `test_next_turn_errors_when_no_active`.
- [x] Реализовать `CombatService::nextTurn`.
- [x] Падающий `CombatServiceTest::test_toggle_active_and_back`
      + `test_reinserted_participant_follows_initiative`.
- [x] Реализовать `CombatService::setParticipantActive`.
- [x] Падающий `CombatServiceTest::test_set_initiative_resorts`.
- [x] Реализовать `CombatService::setInitiative`.
- [x] Падающий `CombatServiceTest::test_reveal_hidden`.
- [x] Реализовать `CombatService::revealParticipant`.
- [x] Падающий `CombatServiceTest::test_finish_encounter`.
- [x] Реализовать `CombatService::finishEncounter`.
- [x] Падающий `CombatServiceTest::test_scene_close_finishes_encounter`.
- [x] Реализовать `CombatService::finishForScene` + вызов из
      `SceneLifecycleService::close` (AC-13).
- [x] Падающий `CombatServiceTest::test_participant_left_scene_deactivates`.
- [x] Реализовать `CombatService::deactivateForParticipant` + вызов из
      `SceneParticipantService` (AC-14).
- [x] Все тесты Stage 2 green.

## Stage 3: API и Policy
- [x] Создать `CombatEncounterPolicy` (через `GuardsChronicleMembership` +
      `denyUnlessStoryteller`).
- [x] Добавить строки в `tests/Feature/AccessMatrixTest.php`.
- [x] Падающий `CombatApiTest::test_start_returns_existing`
      + `test_storyteller_sees_hidden`
      + `test_player_sees_placeholder_for_hidden`
      + `test_hidden_keeps_position`.
- [x] Реализовать `CombatController::store`, `show` с `serializeFor`.
- [x] Реализовать остальные методы `CombatController`: `addParticipant`,
      `setActive`, `reveal`, `setInitiative`, `rollInitiative`,
      `nextTurn`, `finish`.
- [x] Создать Requests: `StoreCombatRequest`, `AddParticipantRequest`,
      `SetParticipantActiveRequest`, `RollInitiativeRequest`,
      `SetInitiativeRequest`.
- [x] Добавить маршруты в `routes/api.php`.
- [x] Падающие `CombatApiTest::test_add_duplicate_returns_409`,
      `test_add_non_participant_returns_422`,
      `test_storyteller_only_actions_reject_player`,
      `test_show_after_finish_returns_no_active`,
      `test_roll_resets_turn_and_round`,
      `test_roll_recalculates_all_participants`.
- [x] Все тесты Stage 3 green.

## Stage 4: Finalization (DoD)
- [x] Полный прогон `php artisan test`.
- [x] Прогон `vendor/bin/pint`.
- [x] Прогон `vendor/bin/phpstan analyse`.
- [ ] Ручная проверка через UI: storyteller создаёт секвенцию, добавляет
      участников, роллит инициативу, ходит вперёд, скрывает/раскрывает,
      выбивает/возвращает, пересчитывает инициативу (проверить сброс раунда),
      завершает. Игрок видит трекер без раскрытия скрытых.
- [x] Обновить `spec.md` и `tasks.md` (Status: Completed).