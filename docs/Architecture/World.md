# Мир: идентичность и типизированные сущности

Корневой канон мира — `world_entities` внутри хроники. Имя не является идентификатором: граф, лор, события и память ссылаются на стабильный ID.

ST-справочник фракций/мест/предметов/идей, политика фракций и статьи лора — HTTP + `/world`. Typed `world_events` есть; внутриигровая шкала времени снята (этап 17). Редактор памяти в SPA нет.

## Идентичность

`world_entities`: `chronicle_id`, `entity_type` (`character`, `faction`, `clan`, `coterie`, `circle`, `other`, `location`, `item`, `concept`, `event`), `canonical_name`, `slug`, `short_description`, `status`, `archived_at`.

Индексы: `(chronicle_id, entity_type)`, уникальные `(chronicle_id, slug)` и `(id, entity_type)`.

`world_entity_aliases`: `entity_id`, `chronicle_id`, `alias`, `normalized_alias`, `alias_type` (`canonical`, `aka`, `former`), `language`.

Уникальный `(chronicle_id, normalized_alias)` и один `canonical` на сущность. Составной FK `(entity_id, chronicle_id)` запрещает алиас другой хроники.

## Типизированные таблицы (shared PK)

`characters`, `locations`, `factions`, `clans`, `coteries`, `circles`, `others`, `items`, `concepts`, `world_events`: PK = `world_entities.id`. FK `(id, entity_type)` совпадает с типом identity. CHECK фиксирует `entity_type` таблицы. `chronicle_id` дублируется, чтобы parent/owner/clan/sire не могли ссылаться на другую хронику.

| Таблица | Поля |
|---------|------|
| `characters` | `character_type` (`player`/`npc`/`ghoul`), nullable unique `user_id` (только PC), `clan_entity_id` → `clans`, `sire_character_id`, `domitor_character_id` (обязателен у гуля), generation/ages, nature/demeanor/concept, `lore_clearance_levels` (smallint[]), `is_active` |
| `locations` | `parent_location_id`, `details` (jsonb) |
| `factions` | `parent_faction_id`, `status` |
| `clans` / `coteries` / `circles` | `sect_faction_id` nullable → `factions`, `status` |
| `others` | `status` |
| `items` | `owner_entity_id` nullable → любая `world_entities` той же хроники, `status` |
| `concepts` | `definition` |
| `world_events` | nullable `scene_id`, title, description, `event_type`, status, importance, visibility, approval; без `timeline_id` |

`character_users` нет: один пользователь = один PC (`characters.user_id` unique). NPC и гули: `user_id` пуст. Гуль принадлежит вампиру через `domitor_character_id` (player или NPC той же хроники), не через аккаунт игрока. Сир (`sire_character_id`) — Объятья, не домитор.

HTTP создание персонажей — [[API/Characters]]. Игрок может отправить реплику от своего PC и от гулей этого PC ([[API/Messages]]).

Обратный `belongsTo` с typed-строки на `world_entities` по тому же `id` не объявляется: Eloquent зацикливает пару `HasOne`/`belongsTo` на shared PK. Связь читается как `WorldEntity::character()` / `location()` / `faction()` / `item()` / `concept()` / `event()`.

## Доменный сервис

`App\World\WorldEntityService` в одной транзакции создаёт identity, typed-строку и алиасы.

- `create(..., typed: [])` — slug, canonical alias, aka, typed-строка (включая `characters`)
- `archive` — `status=archived`, без физического DELETE; у персонажа ещё `characters.is_active=false`
- `restore` — `status=active`, `archived_at` сбрасывается; у персонажа `is_active=true`
- `assertClan` — клан персонажа только активный `entity_type=clan`
- `sect_faction_id` у clan/coterie/circle → auto-synced `member_of` к sect-faction
- `findByAlias` / `assertSameChronicle` — chronicle scope
- `addAka` / `syncAka` — дополнительные имена (`aka`); канон только через rename

Физическое удаление запрещено: Eloquent `delete()` бросает `CannotDeleteWorldEntityException`, триггер PostgreSQL отклоняет `DELETE`.

Нормализация имён: `App\World\AliasNormalizer`.

Тесты: `tests/Feature/WorldEntityTest.php`, `tests/Feature/TypedWorldEntityTest.php`, `tests/Feature/CharacterTest.php`, `tests/Feature/CharacterSheetTest.php`, `tests/Feature/CharacterStatTest.php`, `tests/Feature/CharacterBiographyTest.php`, `tests/Feature/CharacterBioIndexTest.php`, `tests/Feature/WorldRelationTypeTest.php`, `tests/Feature/WorldRelationTest.php`, `tests/Feature/CharacterRelationshipTest.php`, `tests/Feature/CharacterAffiliationTest.php`, `tests/Feature/WorldDirectoryTest.php`, `tests/Feature/WorldEventTest.php`, `tests/Feature/LoreEntryTest.php`, `tests/Feature/WorldGraphRagTest.php`, `tests/Unit/World/AliasNormalizerTest.php`.

## Лист персонажа

Характеристики — отдельные строки `character_stats`, не JSON-лист и не колонка на каждый stat. Unique `(character_id, category, stat_key)`. Специализации — `character_stat_specializations`.

`App\Character\CharacterStatService` пишет строки. `CharacterSheetReader` собирает полный лист или урезанный набор (категория / ключ / минимум value) для секции identity [[Architecture/Context|Context Assembler]].

Каталоги `disciplines` / `discipline_powers` (ruleset + required_level + `rule_key`, без таблицы правил этапа 19). Изученное — `character_disciplines` и `character_powers`; JSONB только у параметров силы.

Текущее состояние — one-to-one `character_status` (PK = character_id) плюс эффекты и журнал. `CharacterStatusService::apply` пишет current row и историю в одной транзакции с optimistic `revision`. Sheet HTTP пишет `blood_pool` / `temporary_willpower` через тот же сервис; hunger с листа V20 не отдаётся. Здоровье листа — `character_health_boxes` (`CharacterHealthService`); merits/flaws — `character_merits_flaws`; XP — `characters.experience`. Контракт: [[API/Characters]].

Каноническая биография — one-to-one `character_biographies` плюс immutable `character_biography_versions`. `CharacterBiographyService::publish` пишет current row и snapshot версии в одной транзакции; поисковый индекс не является каноном. Таблицы `character_goals` нет. Лист публикует биографию через `PUT /api/characters/{id}/biography` и сразу пересобирает `character_bio_chunks`. Место в мире — `PUT /api/characters/{id}/place` (ST): секта и гавань через `CharacterAffiliationService::setSect` / `setHaven`, клан через identity, допуск к лору — `characters.lore_clearance_levels`. Справочник мира (включая `PUT` entities) — [[API/World]], [[Features/World]]. Статьи лора — [[API/Lore]]. Редактор памяти — backlog.

Производный индекс — `character_bio_chunks` (отдельный HNSW, не `rag_chunks`). `CharacterBioIndexer` индексирует только сохранённую версию; `CharacterBioSearcher` всегда фильтрует по `character_id`.

## Типы связей

Каталог `world_relation_types` задаёт семантику рёбер: allowed source/target `WorldEntityType`, `symmetric`, `transitive`, `inverse_key`, `default_weight`, `enabled`. Ключи: knows, member_of, located_at, owns, controls, allied_with, hostile_to, created, participated_in, caused, witnessed, affiliated_with, occurred_at, **part_of**.

`part_of`: source `concept` → target directory-тип (идея, фракция, клан, место, предмет…). `transitive: true`. Виртуальная инверсия `contains` хранится в `inverse_key` типа, отдельной строки `contains` в БД нет; `WorldRelationService::neighbors` и GraphRAG CTE обходят входящие `part_of` как «содержит», без второй строки в `world_relations`.

`WorldRelationTypeValidator` отклоняет disabled-типы, смешанные хроники и нелегальные направления/типы узлов. Новый тип — INSERT строки, без миграции схемы графа.

## Граф

`world_relations` — направленные рёбра внутри хроники. Симметрия типа учитывается query layer (`WorldRelationService::neighbors` и запрет обратного дубля), обратная строка не создаётся. Self-loop, межхроникальные рёбра и активные дубли запрещены. `replaceAmong` меняет взаимоисключающие ключи для одной пары (политика фракций).

`character_relationships` — typed extension ребра character→character без метрик и без журнала (набор шкал не зафиксирован). A→B ≠ B→A.

`character_affiliations` — typed extension character→directory entity с stance, метриками 0–5 и журналом `character_affiliation_changes`. Секта листа — единственный активный `member` к любой активной `faction` (`member_of`); гавань — единственный активный `resident` к location (`located_at`). Котерия — отдельный affiliation, слот секты не заменяет.

`member_of`: sources `character`/`clan`/`coterie`/`circle`/`faction` → target `faction`.

События — typed `world_events` плюс participants/sources. Место, причина, свидетели и участие — рёбра `occurred_at` / `caused` / `witnessed` / `participated_in`. Таблицы `chronicle_timeline` нет.

Политика сект — симметричные `hostile_to` / `allied_with` между фракциями (одна строка на пару, вражда и союз взаимоисключающие). Не матрица НПС.

Лор ссылается на мир через `lore_entry_entities`. Память стыкуется только явными мостами `memory_node_*` (`MemoryBridgeService`); текст воспоминания не становится каноном.

GraphRAG мира — `WorldGraphRag`: seed из NPC, сцены, алиасов, лора и мостов памяти; `WITH RECURSIVE` по `world_relations` (depth ≤2, chronicle, temporal `started_at` по часам приложения, cycle guard). Для NPC storyteller-only события не обходятся, lore chunks только по grants. Context Assembler вставляет bundle в блок `## World`.

Схема таблиц: [[Architecture/Database]].
