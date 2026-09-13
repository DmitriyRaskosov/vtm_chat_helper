# Игровые сессии и сцены API

Игровая история организована как `Chronicle → GameSession → Scene → Message`. В одной хронике одновременно активна не более одной игровой сессии и одной сцены внутри неё. Несколько хроник могут иметь независимые активные встречи.

Membership/RBAC нет: текущий рассказчик управляет всеми хрониками. `chronicle_id` всё равно передаётся как смысловой scope доменных запросов.

## GET /api/game-sessions/active

**Auth:** sanctum, любая роль.

**Query:** `chronicle_id` — optional integer, exists `chronicles`. Если не передан, используется служебная хроника (первая по `id`, созданная backfill-миграцией).

Возвращает активную игровую сессию выбранной хроники, все её сцены и `active_scene_id`. В ответе есть `chronicle_id`. Закрытые сцены остаются в списке и доступны для чтения.

## POST /api/game-sessions

**Auth:** sanctum + `storyteller`.

| Поле | Правила |
|------|---------|
| `title` | required string, max 120 |
| `chronicle_id` | optional integer, exists `chronicles`; по умолчанию служебная хроника |

Создаёт и активирует новую сессию с начальной активной сценой в указанной хронике. Предыдущая активная сессия **этой** хроники архивируется; все её незакрытые сцены, включая `draft`, закрываются. Активные встречи других хроник не затрагиваются.

## POST /api/game-sessions/{gameSession}/scenes

**Auth:** sanctum + `storyteller`.

| Поле | Правила |
|------|---------|
| `title` | required string, max 120 |
| `description` | optional string, max 2000 |
| `activate` | optional boolean, default `true` |

Активированная новая сцена переводит предыдущую активную сцену в `draft`, чтобы к ней можно было вернуться.

## PATCH /api/scenes/{scene}/activate

**Auth:** sanctum + `storyteller`.

Активирует `draft`-сцену текущей активной сессии. Закрытую сцену активировать нельзя.

## PATCH /api/scenes/{scene}/close

**Auth:** sanctum + `storyteller`.

Закрывает сцену и, если есть `scene_contexts`, фиксирует `frozen_revision`. Сообщения остаются доступны через [[API/Messages]], но новые сообщения и Copilot для закрытой сцены запрещены. LLM при закрытии не вызывается. Дальнейшие правки context/participants дают **409**.

## GET /api/scenes/{scene}/context

**Auth:** sanctum + `storyteller`.

Возвращает канонический snapshot сцены. Если строки ещё нет: `revision=0`, поля пустые.

## PUT /api/scenes/{scene}/context

**Auth:** sanctum + `storyteller`.

| Поле | Правила |
|------|---------|
| `expected_revision` | required integer ≥ 0 |
| `location_entity_id` | optional nullable; должен быть `locations.id` той же хроники |
| `atmosphere` | optional nullable string, max 2000 |
| `situation` | optional nullable string, max 2000 |
| `storyteller_notes` | optional nullable string, max 4000 |

Optimistic lock: несовпадение revision или закрытая сцена → **409**. Чужая хроника у location → **409**.

## GET /api/scenes/{scene}/participants

**Auth:** sanctum + `storyteller`.

Список входов, включая покинувших (`is_current=false`). Каждый item содержит `character_id`, `character_name`, `character_type`, role/visibility и timestamps; UI Copilot выбирает текущих участников с `character_type=npc`.

Рассказчик добавляет и убирает участников в `ChatView` (блок «На сцене»), без ручного POST.

## POST /api/scenes/{scene}/participants

**Auth:** sanctum + `storyteller`.

| Поле | Правила |
|------|---------|
| `character_id` | required, exists `characters` |
| `role` | required `npc` \| `player` \| `extra` |
| `visible` | optional boolean, default `true` |

Повторный enter текущего участника идемпотентен. Персонаж другой хроники → **409**. Закрытая сцена → **409**.

## PATCH /api/scenes/{scene}/participants/{character}

**Auth:** sanctum + `storyteller`.

Помечает текущего участника как покинувшего (`is_current=false`, `left_at`). Повторный leave → **422**. Повторный enter создаёт новую current-строку.

См. [[Features/Scenes]], [[Project/Roles]].
