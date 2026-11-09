# Plan: Боевая Секвенция Сцены (Этап 1: Ядро)

**Status:** Completed
**Date:** 2026-11-09

## 1. Подход и архитектура

**Модель данных.**
`combat_encounters` — сессионная сущность сцены. Жизненный цикл через
`status` (enum `CombatEncounterStatus: Active='active', Finished='finished'`),
без `deleted_at` — в проекте soft delete только у Character и Message,
для сессионных сущностей принят стиль Scene/GameSession. Дети
(`combat_participants`) удаляются каскадно по FK.

`combat_participants` — участники боя. Флаги:
- `is_active` — участвует в очереди ходов (false = выбыл);
- `is_hidden` — скрыт от игроков (засада).

**Уникальность:**
- partial unique index `combat_encounters (scene_id) WHERE status = 'active'`
  — единственная активная секвенция на сцену (AC-1);
- unique `combat_participants (combat_encounter_id, character_id)` — защита
  от дублей (AC-2).

**Расчёт инициативы.** Берём `$character->stats` (jsonb → array). Для
каждого ключа из `stat_keys` (default `['dexterity', 'wits']`):
`CharacterStatsService::filledDots($stats[$key] ?? null, 0)` — учитывает
постоянные (1) и временные «красные» (2) точки. К сумме **прибавляем**
`CharacterStatsService::legacyState($stats)['health_penalty']` — значение
уже отрицательное (−1, −2, …). Не путать со «вычитанием штрафа».

**Семантика `rollInitiative`.** Пересчитывает инициативу всех участников
(включая уже имеющих значение — AC-15), сортирует по AC-5, **сбрасывает
`current_turn_index = 0` и `current_round = 1`**. Это «начинаем бой с начала».
Если storyteller пересчитывает посреди боя — он осознанно начинает заново.
UI-этап 2 может добавить подтверждающий диалог.

**Авторизация.** `CombatEncounterPolicy` использует
`GuardsChronicleMembership` + `denyUnlessStoryteller()` по прецеденту
`ScenePolicy::update`. Хроника — из графа
`CombatEncounter → Scene → GameSession → Chronicle`, не из URL.
Метод-аналог `ScenePolicy::chronicleForScene`.

**Фильтрация скрытых — не в Policy.** Сериализация в контроллере через
приватный метод `serializeFor(User $user, CombatEncounter $encounter)`.
Прецедент — `DiceRollController::includeHidden()`.

**Роуты.** Короткие, хронику достаём из графа:
- `POST   /api/scenes/{scene}/combat` — создание (idempotent).
- `GET    /api/scenes/{scene}/combat` — чтение состояния.
- `POST   /api/combat/{combat}/participants` — добавить.
- `PATCH  /api/combat/{combat}/participants/{participant}/active`
  — тело `{"is_active": bool}`, идемпотентный `setParticipantActive`.
- `POST   /api/combat/{combat}/participants/{participant}/reveal`
- `POST   /api/combat/{combat}/participants/{participant}/initiative`
- `POST   /api/combat/{combat}/roll-initiative`
- `POST   /api/combat/{combat}/next-turn`
- `POST   /api/combat/{combat}/finish`

**Интеграция с закрытием сцены.** Транзакция закрытия и
`SummarizeSceneDiaryJob` живут в `SceneLifecycleService::close`, контроллер
только делегирует. После коммита транзакции и до
`scheduleSummary()` сервис вызывает `CombatService::finishForScene($scene)`
— AC-13.

**Интеграция с покиданием сцены.** В `SceneParticipantService` в метод,
проставляющий `left_at`, добавить вызов
`CombatService::deactivateForParticipant($participant)` — AC-14.

**Idempotent create.** `startEncounter(Scene $scene)`:
1. Ищем активную секвенцию сцены; если есть — возвращаем.
2. Иначе create; при `UniqueConstraintViolationException` — race-safe
   перечитываем и возвращаем существующую.

**Порядок до первого ролла.** После `addParticipant` инициатива = null.
Чистая `sortByInitiative` **не сортирует**, если у всех участников
`initiative` null: возвращает порядок добавления (`id`) и не ходит в БД.
После первого `rollInitiative` сортировка включается. Если участника
добавили после ролла — его `initiative = null`, он идёт в конец
(сначала непустые по убыванию, потом null). Выбывшие (`is_active = false`)
уходят в конец и пропускаются `nextTurn`.

**Индекс хода при пересортировке (AC-9, AC-11).**
`setParticipantActive` и `setInitiative` после изменения данных вызывают
`reindexCurrentTurn`: запоминают `character_id` участника на текущем
`current_turn_index`, применяют `sortByInitiative` и записывают новый
индекс этого же `character_id`. Ход не перескакивает на другого.
`rollInitiative` индекс не сохраняет: сбрасывает его в 0 вместе с раундом.

## 2. Затрагиваемые файлы

**Infra / DB:**
- `database/migrations/2026_11_09_000001_create_combat_encounters_table.php`
  — id, scene_id (FK cascadeOnDelete), status string(20) default 'active',
  current_round int default 1, current_turn_index int default 0,
  stat_keys jsonb nullable, started_at timestamp, ended_at timestamp
  nullable, timestamps. Partial unique index по `(scene_id)`
  `WHERE status = 'active'` через `DB::statement`.
- `database/migrations/2026_11_09_000002_create_combat_participants_table.php`
  — id, combat_encounter_id (FK cascadeOnDelete), character_id (FK
  cascadeOnDelete), initiative int nullable, is_active bool default true,
  is_hidden bool default false, timestamps. Unique
  `(combat_encounter_id, character_id)`.

**Backend:**
- `app/Enums/CombatEncounterStatus.php` — `Active='active'`, `Finished='finished'`.
- `app/Models/CombatEncounter.php` — casts (`status` → enum, `stat_keys` →
  array, `started_at`/`ended_at` → datetime), relations (`scene`,
  `participants`, `participants.character`), scope `active()`.
- `app/Models/CombatParticipant.php` — casts (`is_active`, `is_hidden` → bool,
  `initiative` → int), relations (`encounter`, `character`).
- `app/Combat/CombatService.php`:
  - `startEncounter(Scene): CombatEncounter`
  - `addParticipant(CombatEncounter, Character, bool $isHidden = false): CombatParticipant`
  - `rollInitiative(CombatEncounter, array $statKeys = ['dexterity','wits']): CombatEncounter`
  - `nextTurn(CombatEncounter): CombatEncounter`
  - `setParticipantActive(CombatParticipant, bool $isActive): CombatParticipant`
  - `revealParticipant(CombatParticipant): CombatParticipant`
  - `setInitiative(CombatParticipant, int): CombatParticipant`
  - `finishEncounter(CombatEncounter): CombatEncounter`
  - `finishForScene(Scene): void`
  - `deactivateForParticipant(SceneParticipant): void`
  - `sortByInitiative(Collection $participants, array $statKeys): Collection`
    — чистая функция, без запросов к БД
  - `reindexCurrentTurn` — пересчёт `current_turn_index` по `character_id`
- `app/Policies/CombatEncounterPolicy.php` — `viewAny`, `view`, `create`,
  `update`; storyteller-only для create/update.
- `app/Http/Controllers/CombatController.php` — store, show, addParticipant,
  setActive, reveal, setInitiative, rollInitiative, nextTurn, finish;
  приватный `serializeFor(User, CombatEncounter): array`.
- `app/Http/Requests/StoreCombatRequest.php`
- `app/Http/Requests/AddParticipantRequest.php`
- `app/Http/Requests/SetParticipantActiveRequest.php` — `is_active: bool`
- `app/Http/Requests/RollInitiativeRequest.php`
- `app/Http/Requests/SetInitiativeRequest.php`
- `routes/api.php` — новые маршруты в общей группе.
- `app/Scene/SceneParticipantService.php` — хук на
  `CombatService::deactivateForParticipant()` (AC-14).
- `app/Scene/SceneLifecycleService.php` — в `close()` после транзакции и
  до `scheduleSummary()` вызвать `CombatService::finishForScene($scene)`
  (AC-13).

**Tests:**
- `tests/Feature/CombatServiceTest.php`
- `tests/Feature/CombatApiTest.php`
- `tests/Feature/AccessMatrixTest.php` — строки по новым роутам.

## 3. Верификация AC

| AC | Как проверяется |
|---|---|
| AC-1 | `CombatServiceTest::test_start_creates_active_encounter`, `test_start_is_idempotent`, `test_start_race_safe` + `CombatApiTest::test_start_returns_existing` |
| AC-2 | `CombatApiTest::test_add_participant_success`, `test_add_duplicate_returns_409`, `test_add_non_participant_returns_422`, `test_add_sets_initiative_null` |
| AC-3 | `CombatApiTest::test_roll_with_default_stats`, `test_roll_with_custom_stats` |
| AC-4 | `CombatServiceTest::test_initiative_sums_stats`, `test_initiative_counts_temporary_dots`, `test_initiative_adds_negative_health_penalty` (штраф −2 → 8 → 6) |
| AC-5 | `CombatServiceTest::test_sort_desc_by_initiative`, `test_sort_ties_by_first_stat`, `test_inactive_go_to_end`, `test_no_sort_before_first_roll` |
| AC-6 | `CombatApiTest::test_storyteller_sees_hidden` |
| AC-7 | `CombatApiTest::test_player_sees_placeholder_for_hidden`, `test_hidden_keeps_position` |
| AC-8 | `CombatServiceTest::test_next_turn_skips_inactive`, `test_next_turn_wraps_round_no_reroll`, `test_next_turn_errors_when_no_active` |
| AC-9 | `CombatServiceTest::test_toggle_active_and_back`, `test_reinserted_participant_follows_initiative` — `setParticipantActive`, `reindexCurrentTurn` по `character_id` |
| AC-10 | `CombatApiTest::test_reveal_hidden` |
| AC-11 | `CombatServiceTest::test_set_initiative_resorts` — после правки `reindexCurrentTurn` сохраняет ход того же `character_id` |
| AC-12 | `CombatApiTest::test_finish_encounter`, `test_show_after_finish_returns_no_active` |
| AC-13 | `CombatServiceTest::test_scene_close_finishes_encounter` + feature-тест на `SceneController::close` |
| AC-14 | `CombatServiceTest::test_participant_left_scene_deactivates` + feature-тест на `SceneParticipantService` |
| AC-15 | `CombatServiceTest::test_roll_resets_turn_and_round`, `test_roll_recalculates_all_participants` |

## 4. Риски

- **Риск:** штраф от ранений вычтут дважды (он уже отрицательный).
  **Митигация:** тест `test_initiative_adds_negative_health_penalty` с явным
  значением −2 → 8 → 6.
- **Риск:** гонка при `startEncounter` (два storyteller-запроса).
  **Митигация:** partial unique на БД + catch на unique violation.
- **Риск:** `stat_keys` содержит несуществующие slug'и.
  **Митигация:** `filledDots(null, 0)` вернёт 0 (как в макросах).
- **Риск:** `stats` повреждён (например, `health` — не массив).
  **Митигация:** `legacyState()` защищён внутри `CharacterStatsService`.
- **Риск:** `rollInitiative` посреди боя сбрасывает раунд — storyteller может
  не ожидать. **Митигация:** явный тест
  `test_roll_resets_turn_and_round`; UI-этап 2 добавит подтверждение.
- **Риск:** `SceneParticipantService` и `SceneController::close` — точки
  входа, которые легко сломать. **Митигация:** feature-тесты на AC-13/AC-14.
- **Риск:** если у участника `stats` = null — инициатива = 0 + penalty (0).
  **Митигация:** `filledDots(null, 0)` и `legacyState([])` уже это
  обрабатывают; добавить тест `test_initiative_with_empty_stats`.

## 5. Допущения

- [Assumption] Роуты короткие (`/api/scenes/{scene}/combat`,
  `/api/combat/{combat}/...`). Хроника — из графа
  `encounter → scene → gameSession → chronicle`.
- [Assumption] Soft delete для секвенции не используется — только
  `status = finished`.
- [Assumption] `rollInitiative` можно вызывать в любой момент, но он
  сбрасывает раунд и индекс хода к началу (AC-15, AC-8).
- [Assumption] `nextTurn` в Этапе 1 не трогает `active_character_id` сцены.
  Механизм «ВАШ ХОД» работает независимо; синхронизация — Этап 3.
- [Assumption] До первого `rollInitiative` участники показываются в порядке
  добавления, `initiative = null`.