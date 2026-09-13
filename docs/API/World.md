# Мир (API)

ST-only справочник мира и политика фракций. Персонажи создаются через [[API/Characters]]. Статьи лора — [[API/Lore]].

**Auth:** sanctum + `storyteller`. Игрок — **403**.

Хроника: optional `chronicle_id`, иначе служебная первая хроника (`Chronicle::resolveId`). Сущность другой хроники — **404**.

## GET /api/world/entities

Активные `faction` / `location` / `item` / `concept` и отдельно `archived`. Персонажи и события не входят.

Элемент: `id`, `entity_type`, `canonical_name`, `short_description`, `subtype` (`faction_type` / `location_type` / `item_type` / `concept_type`), `status`.

## POST /api/world/entities

**201.** Body: `canonical_name`, `entity_type` (`faction`/`location`/`item`/`concept`), optional `subtype`, `short_description`, `chronicle_id`.

Дефолты subtype: фракция `other`, место `site`, предмет `mundane`, идея `other`. Дубликат имени/алиаса — **422**. `character`/`event` — **422**.

## PUT /api/world/entities/{entity}

**200.** Только активные `faction` / `location` / `item` / `concept` той же хроники. Body: `canonical_name` (required), optional `short_description`, `subtype`. `entity_type` в body запрещён (**422**). Archived / character / event — **422**. Дубликат имени/алиаса — **422**. Имя обновляет канонический alias через `WorldEntityService::update`.

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

## Ошибки

- 401 без Bearer
- 403 не рассказчик
- 404 сущность/ребро другой хроники
- 409 mixed chronicle
- 422 валидация, дубликат имени, нелегальный тип
