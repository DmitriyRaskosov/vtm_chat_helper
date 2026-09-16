# Мир (API)

ST-only справочник мира и политика фракций. Персонажи создаются через [[API/Characters]]. Статьи лора — [[API/Lore]].

**Auth:** sanctum + `storyteller`. Игрок — **403**.

Хроника: optional `chronicle_id`, иначе служебная первая хроника (`Chronicle::resolveId`). Сущность другой хроники — **404**.

## GET /api/world/entities

Активные directory-типы (`faction`, `clan`, `coterie`, `circle`, `other`, `location`, `item`, `concept`) и отдельно `archived`. Персонажи и события не входят.

Элемент: `id`, `entity_type`, `canonical_name`, `short_description`, `aliases` (только aka), `parent_faction_id` (только у `faction`, иначе `null`), `sect_faction_id` (только у `clan`/`coterie`/`circle`, иначе `null`), `status`.

## POST /api/world/entities

**201.** Body: `canonical_name`, `entity_type` (`faction`/`clan`/`coterie`/`circle`/`other`/`location`/`item`/`concept`), `aliases` (список aka), `parent_faction_id` (только фракция; `null` — без родителя), `sect_faction_id` (только clan/coterie/circle; `null` — без секты), `short_description`, `chronicle_id`.

Секты (Камарилья, Шабаш, Анархи) — обычные `entity_type=faction`. При сохранении `sect_faction_id` у clan/coterie/circle сервер upsert'ит auto-synced `member_of` к sect-faction; при `null` снимает только auto-synced `member_of`.

## PUT /api/world/entities/{entity}

**200.** Только активные directory-сущности той же хроники. Body: `canonical_name` (required); optional `short_description`, `aliases` (заменяет набор aka), `parent_faction_id` (только фракция; `null` снимает родителя), `sect_faction_id` (только clan/coterie/circle; `null` снимает секту и auto-synced `member_of`). `entity_type` в body запрещён (**422**). Archived / character / event — **422**. Дубликат имени/алиаса — **422**. Self-parent — **422**.

## POST /api/world/entities/{entity}/archive

**200.** `WorldEntityService::archive`. Персонажа этим путём скрыть нельзя (**422**) — [[API/Characters]].

## POST /api/world/entities/{entity}/restore

**200.** Обратная операция.

## GET /api/world/faction-relations

Активные `hostile_to` / `allied_with` между фракциями хроники.

Элемент: `id`, `relation_key`, `source_entity_id`, `source_name`, `target_entity_id`, `target_name`, `note`, `ended_at`.

## POST /api/world/faction-relations

Body: `source_entity_id`, `target_entity_id`, `relation_key` (`hostile_to`|`allied_with`), optional `note`.

Оба конца — активные фракции той же хроники. Тип симметричный: обратная пара не создаёт вторую строку. Для одной пары фракций вражда и союз взаимоисключающие: новая запись завершает предыдущую. Повтор того же ключа — **200** (существующее ребро). Новое ребро — **201**. Не фракция / скрытая фракция — **422**.

## POST /api/world/faction-relations/{relation}/end

**200.** Ставит `ended_at`. Только политические рёбра фракция–фракция.

## GET /api/world/relations

Активные directory-рёбра хроники (`controls`, `owns` и др. по allowlist). Не политика фракций.

Query: `keys` (required) — через запятую, например `controls,owns,part_of`. Без `keys` — **422**. Неизвестный ключ в `keys` — **422**.

Элемент: `id`, `relation_key`, `source_entity_id`, `source_name`, `target_entity_id`, `target_name`, `note`, `ended_at`. Оба конца — активные directory-сущности (не character, не event).

## POST /api/world/relations

Body: `source_entity_id`, `target_entity_id`, `relation_key` (`controls`|`owns`|`part_of`), optional `chronicle_id`.

`WorldRelationService::relate` (не `replaceAmong`). Валидатор каталога отвергает нелегальные пары (например location→faction для `controls`). Дубликат активного того же ключа и пары — **200** (существующее). Новое — **201**. Смешанные хроники — **409**. Политические ключи — **422**.

Направление для UI: `controls` — фракция → место; `owns` — фракция → предмет; `part_of` — идея → целое (идея, фракция, клан, место и т.д.).

## POST /api/world/relations/{relation}/end

**200.** Ставит `ended_at`. Только `controls` / `owns` / `part_of`. Политика и прочие ключи — **422**. Чужая хроника — **404**.

## POST /api/world/events

Тонкий ST API вокруг `WorldEntityService::create(Event)` + typed `world_events`. Не полный CRUD; списка/редактора в SPA нет.

**201.** Body: `title` (required); optional `description`/`summary`, `scene_id` (та же хроника), `event_type` (`social`|`violence`|`discovery`|`ritual`|`political`|`other`, default `other`), `importance` 0–5, `visibility`, `chronicle_id`.

## POST /api/world/events/{event}/participants

**201.** Body: `entity_id`, `role` (`actor`|`victim`|`witness`|`organizer`|`mentioned`|`other`). Обёртка `WorldEventService::addParticipant`.

## POST /api/world/events/{event}/sources

**201.** Body: `message_id` и/или `scene_id` (хотя бы одно). Обёртка `WorldEventService::addSource`. Excerpt для message — обрезка body.

Accept экстрактора сцены вызывает те же сервисы напрямую, не этот HTTP.

## Ошибки

- 401 без Bearer
- 403 не рассказчик
- 404 сущность/ребро другой хроники
- 409 mixed chronicle
- 422 валидация, дубликат имени, нелегальный тип
