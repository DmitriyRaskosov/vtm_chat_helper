# Plan: Боевая Секвенция (Этап 4: Дневник и Бой)

**Status:** Completed
**Date:** 2026-11-09

## 1. Подход и архитектура

**Схема БД.**
Добавляем колонку `combat_encounter_id` (nullable, FK) в таблицу `character_diary_entries`. Наличие ID = L1/L0 является частью боевой сводки.

**Удаление ограничения 1 L1 на сцену.**
Сейчас `DiarySummarizerService::summarizeForNpc` содержит проверку: если для NPC на сцене уже есть хоть одна запись `level = 1`, сервис возвращает `false` (лог `diary.summarize.already_exists`). Это блокирует «слоёный пирог». 
Мы **полностью удаляем** эту проверку. Вместо неё `summarizeForNpc` будет делать следующее:
1. Находит `to_message_id` последнего созданного L1 для этого NPC на этой сцене (если L1 нет, `to_message_id = 0`).
2. Выбирает L0, у которых `from_message_id > $lastToMessageId`, `is_stale = false`, и `combat_encounter_id` соответствует переданному параметру (ID боя или `null`).
3. Если таких L0 набралось `>= 2`, создает из них новый L1 и привязывает к нему `combat_encounter_id` (если передан).
4. Если L0 меньше двух, ничего не создает.

**Логика инкрементальной суммаризации (Решение проблемы дублей).**
Вместо проверки `if (L1 exists) return false`, мы ищем `max(to_message_id)` среди уже существующих L1 для этого NPC на этой сцене. При генерации нового L1 сервис берет только те L0, у которых `from_message_id > last_L1_to_message_id`.

**`DiaryWriterService` (L0).**
В метод записи L0 добавляется проверка: есть ли у сцены активная `CombatEncounter`. Если да — `combat_encounter_id` передается в модель.

**`DiarySummarizerService` (L1).**
Метод `summarizeForNpc` дополняется параметрами:
- `?CombatEncounter $combat = null` (фильтр: если передан, берем только L0 с этим `combat_id`; если null — только L0 с `combat_id = null`).
- `?int $characterId = null` (если нужно обработать только одного NPC).
- Логика поиска L0: `where('from_message_id', '>', $lastL1?->to_message_id ?? 0)` + фильтр по `combat_encounter_id`.
- Сохранение L1: передача `combat_encounter_id` в создаваемую запись.
- Удалить проверку existingL1 (вместе с логом diary.summarize.already_exists), заменить на поиск lastL1.to_message_id.

**Интеграция с боем.**
- `CombatService::startEncounter`: вызывает `SummarizeSceneDiaryJob::dispatch($sceneId)` (без параметра combat, чтобы зафиксировать пре-боевые L0).
- `CombatService::finishEncounter`: вызывает `SummarizeSceneDiaryJob::dispatch($sceneId, $combatId)` (суммаризирует боевые L0).
- `SceneParticipantService` (при выходе): если есть активный бой, вызывает `SummarizeSceneDiaryJob::dispatch($sceneId, $combatId, $characterId)`.
- `SceneLifecycleService::close`: вызывает `SummarizeSceneDiaryJob::dispatch($sceneId)` (без combat, чтобы добрать хвосты после боя).

## 2. Затрагиваемые файлы

**Infra / DB:**
- `database/migrations/xxxx_add_combat_encounter_id_to_character_diary_entries_table.php` — FK.
- `app/Models/CharacterDiaryEntry.php` — добавить в `$fillable`.

**Backend:**
- `app/Diary/DiaryWriterService.php` — внедрение поиска активной секвенции и передача ID в L0.
- `app/Diary/DiarySummarizerService.php` — переработка `summarizeForNpc` (инкрементальность, фильтры, отказ от `existingL1` в пользу `to_message_id`).
- `app/Jobs/SummarizeSceneDiaryJob.php` — добавить параметры в конструктор.
- `app/Combat/CombatService.php` — вызовы джобы при старте и финише.
- `app/Scene/SceneParticipantService.php` — вызов джобы с параметром `characterId` при выходе во время боя.

**Tests:**
- `tests/Feature/CombatDiaryTest.php` — тесты слоёной суммаризации.

## 3. Верификация AC

| AC | Как проверяется |
|---|---|
| AC-1 | `CombatDiaryTest::test_l0_gets_combat_id` |
| AC-2 | `CombatDiaryTest::test_combat_start_summarizes_pre_combat_l0` |
| AC-3 | `CombatDiaryTest::test_combat_finish_summarizes_combat_l0` |
| AC-4 | `CombatDiaryTest::test_scene_close_summarizes_only_new_l0` (проверка, что старые L0 не попали в новый L1) |
| AC-5 | `CombatDiaryTest::test_participant_leave_during_combat_summarizes_own_l0` |
| AC-6 | `CombatDiaryTest::test_no_l1_from_single_l0` (касается и обычных сцен, и боевых) |

## 4. Риски

*   **Риск:** `DiaryWriterService` пишет L0 с задержкой через очередь. Бой может закончиться раньше, чем отработает воркер, и L0 запишется как "пре-боевой".
    **Митигация:** `CombatService::finishEncounter` должен вызывать `WriteDiaryJob` синхронно (или форсировать выполнение) до запуска `SummarizeSceneDiaryJob`, чтобы все сообщения боя успели стать L0.
*   **Риск:** Сложная логика поиска `lastL1`. 
    **Митигация:** Четкий тест `test_scene_close_summarizes_only_new_l0`.

## 5. Допущения

- [Assumption] `SummarizeSceneDiaryJob` (с параметрами) будет идемпотентной. Если запустить её дважды с одним и тем же `combatId`, она увидит, что L1 за этот кусок уже есть (по `to_message_id`), и ничего не сделает.