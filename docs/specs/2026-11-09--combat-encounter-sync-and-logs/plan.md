# Plan: Боевая Секвенция (Этап 3: Синхронизация и Логи)

**Status:** Completed
**Date:** 2026-11-09

## 1. Подход и архитектура

**База данных.**
Для изоляции логов и подготовки дневника к Этапу 4 добавляются две миграции:
1. `add_combat_encounter_id_to_scene_logs_table` — колонка `combat_encounter_id` (nullable, FK на `combat_encounters` cascadeOnDelete).
2. `add_combat_encounter_id_to_character_diary_entries_table` — колонка `combat_encounter_id` (nullable, FK).

**Синхронизация сцены.**
В `CombatService` методы `nextTurn` и `finishEncounter` будут обновлять `active_character_id` в модели `Scene` (или `GameSession`, смотря где он сейчас хранится, проверить в `SceneLifecycleService`).

**WebSockets (Reverb).**
Создаются три broadcast-события (реализуют `ShouldBroadcast`):
*   `CombatStartedEvent` (канал `private-chronicle.{id}`).
*   `CombatTurnChangedEvent` (канал `private-chronicle.{id}`).
*   `CombatParticipantUpdatedEvent` (канал `private-chronicle.{id}`).

События диспатчатся из `CombatController` или `CombatService` после успешных транзакций.

**Лог бросков.**
В `DiceRollController` при создании записи в `scene_logs` проверяем наличие активной секвенции для сцены. Если есть — сохраняем `combat_encounter_id`. Это автоматически подхватится Генератором Событий.

**Frontend.**
*   В `useCombatTracker.js` убираем `setInterval` (поллинг).
*   Настраиваем Laravel Echo (если еще не настроен) для слушания канала хроники.
*   При получении событий обновляем локальный `reactive` стейт.
*   В компоненте лога боя добавляем фильтр: если есть `activeCombat`, показываем только записи с этим `combat_encounter_id`, иначе все.

## 2. Затрагиваемые файлы

**Infra / DB:**
*   `database/migrations/xxxx_add_combat_encounter_id_to_scene_logs_table.php`
*   `database/migrations/xxxx_add_combat_encounter_id_to_character_diary_entries_table.php`
*   `app/Models/SceneLog.php` — добавить fillable.
*   `app/Models/CharacterDiaryEntry.php` — добавить fillable.

**Backend:**
*   `app/Events/CombatStartedEvent.php` — новый ивент.
*   `app/Events/CombatTurnChangedEvent.php` — новый ивент.
*   `app/Events/CombatParticipantUpdatedEvent.php` — новый ивент.
*   `app/Combat/CombatService.php` — добавить вызовы `broadcast()` и обновление `active_character_id`.
*   `app/Http/Controllers/CombatController.php` — обернуть вызовы сервиса в `DB::transaction` и диспатчить ивенты (или перенести в сервис).
*   `app/Http/Controllers/DiceRollController.php` — внедрить поиск активного `combat_encounter_id` при записи лога.

**Frontend:**
*   `frontend/src/composables/useCombatTracker.js` — удалить поллинг, добавить `window.Echo.private(...)`.
*   `frontend/src/components/chat/CombatLog.vue` (или где рендерится лог) — внедрить фильтрацию.
*   `frontend/src/composables/useChatPanels.js` — если нужно, обновить логику.

**Tests:**
*   `tests/Feature/CombatSyncTest.php` — тесты на broadcast (`Event::fake()`).
*   `tests/Feature/DiceRollTest.php` — обновить: бросок во время боя получает `combat_encounter_id`.

## 3. Верификация AC

| AC | Как проверяется |
|---|---|
| AC-1 | `CombatSyncTest::test_next_turn_updates_active_character_id` |
| AC-2 | `CombatSyncTest::test_finish_clears_active_character_id` |
| AC-3 | `DiceRollTest::test_roll_during_combat_has_encounter_id` |
| AC-4 | Ручная проверка UI (фильтр в логе). |
| AC-5..8 | `CombatSyncTest::test_events_are_broadcast` (Event::fake) + ручная проверка в двух окнах. |
| AC-9 | `CombatSyncTest::test_diary_entries_get_combat_id` (заглушка, т.к. логика дневника в Этапе 4). |

## 4. Риски

*   **Риск:** `active_character_id` может конфликтовать с ручной передачей хода вне боя.
    **Митигация:** обновляем `active_character_id` только если `combat_encounter` активен. Ручная передача хода игроком (`/api/game-sessions/{id}/turn`) продолжает работать независимо (но мы можем перезаписать её при `nextTurn`).
*   **Риск:** Отсутствие Reverb на локальной машине.
    **Митигация:** обернуть `broadcast()` в `try/catch` (или проверить `config('broadcasting.default')`), чтобы падение сокетов не ломало основной HTTP-запрос.

## 5. Допущения

*   [Assumption] Канал для вещания — `private-chronicle.{chronicle_id}`. Участники хроники имеют к нему доступ.
*   [Assumption] В `GameSession` (или `Scene`) уже есть колонка `active_character_id`, которую мы будем перезаписывать.