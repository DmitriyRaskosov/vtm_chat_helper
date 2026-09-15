# Мир (API)

ST-only справочник мира и политика фракций. Персонажи создаются через [[API/Characters]]. Статьи лора — [[API/Lore]].

**Auth:** sanctum + `storyteller`. Игрок — **403**.

Хроника: optional `chronicle_id`, иначе служебная первая хроника (`Chronicle::resolveId`). Сущность другой хроники — **404**.

## GET /api/world/entities

Активные `faction` / `location` / `item` / `concept` и отдельно `archived`. Персонажи и события не входят.

Элемент: `id`, `entity_type`, `canonical_name`, `short_description`, `subtype` (`faction_type` / `location_type` / `item_type` / `concept_type`), `aliases` (только aka, без канонического имени), `parent_faction_id` (только у `faction`, иначе `null`), `status`.

## POST /api/world/entities

**201.** Body: `canonical_name`, `entity_type` (`faction`/`location`/`item`/`concept`), optional `subtype`, `aliases` (список aka), `parent_faction_id` (только фракция; `null` — без родителя), `short_description`, `chronicle_id`.

Дефолты subtype: фракция `other`, место `site`, предмет `mundane`, идея `other`. Фракции: `sect` / `clan` / `coterie` / `circle` / `other` (`guild` нет, **422**). `circle` — советы, круги влияния; не секта листа, не клан. Иерархия — `parent_faction_id` (та же хроника, не self). Дубликат имени/алиаса — **422**. `character`/`event` — **422**. Не-фракция с `parent_faction_id` — **422**.

## PUT /api/world/entities/{entity}

**200.** Только активные `faction` / `location` / `item` / `concept` той же хроники. Body: `canonical_name` (required); optional `short_description`, `subtype`, `aliases` (заменяет набор aka, канон не трогает; отсутствие поля — aka не менять), `parent_faction_id` (только фракция; `null` снимает родителя; отсутствие поля — не менять). `entity_type` в body запрещён (**422**). Archived / character / event — **422**. Дубликат имени/алиаса — **422**. Имя обновляет канонический alias через `WorldEntityService::update`. Self-parent — **422**.

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
