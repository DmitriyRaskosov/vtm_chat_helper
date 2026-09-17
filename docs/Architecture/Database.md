# Схема базы данных

Каноническая ER-схема PostgreSQL. Graph в Obsidian показывает связи **заметок**, не таблиц: смотреть схему нужно здесь.

Корень всего — `chronicles`. Имя сущности не ID: стабильный ключ — `world_entities.id`. Typed-таблицы (`characters`, `locations`, …) имеют тот же `id`, что и identity.

Канон живёт в обычных таблицах. `*_chunks`, `message_embeddings`, `tsvector` и HNSW — производные индексы, их можно пересчитать. У `world_relation_types` опциональный `inverse_key` (например `part_of` → `contains`) задаёт чтение обратного направления без второй строки в `world_relations`.

Подробности полей: [[Architecture/World]], [[Architecture/Memory]], [[Architecture/Lore]], [[Architecture/Rules]], [[Architecture/Backend]]. Миграции: `database/migrations/`.

## Справочник V20 (`canon_*`)

Глобальный канон сеттинга, **без** `chronicle_id`. Не путать с игровыми `factions` / `clans` (typed `world_entities` хроники). **Секты, кланы, членство и отношения кланов** — PHP-сидеры (`CanonSectSeeder`, `CanonClanSeeder`, …) → таблицы `canon_*`; источник истины — БД, не markdown. **Лор-статьи** — staging в плоском `resources/canon/lore/*.md` (YAML front matter + body); таксономия — поле `category` во front matter, не подпапки; после импорта источник истины — `canon_lore_entries`. Импорт: `php artisan canon:import-lore` (также из `DatabaseSeeder` после сидеров секты/кланы).

```mermaid
erDiagram
  canon_clans ||--o| canon_clans : parent_clan_id
  canon_clans ||--o{ canon_clan_sects : membership
  canon_sects ||--o{ canon_clan_sects : members
  canon_clans ||--o{ canon_clan_relations : from
  canon_clans ||--o{ canon_clan_relations : to
  canon_disciplines ||--o{ canon_clan_disciplines : in_clan
  canon_clans ||--o{ canon_clan_disciplines : disciplines
  canon_disciplines ||--o{ canon_discipline_powers : powers
  canon_paths ||--o{ canon_path_sins : sins
  canon_path_sin_levels ||--o{ canon_path_sins : level
  canon_lore_entries ||--o{ canon_lore_entry_entities : mentions
  canon_lore_entries ||--o{ canon_lore_chunks : derived
```

**Секты и кланы:** `canon_sects`, `canon_clans`, `canon_clan_sects` (история членства: `since_year`/`until_year` nullable smallint, `note` nullable, timestamps, cascade delete с кланом/сектой, unique `clan_id+sect_id+since_year` **NULLS NOT DISTINCT**), `canon_clan_relations` (отношения клан↔клан: `relation_type`, `intensity` −5..5 nullable, годы smallint, `is_symmetric`, `note`/`source` nullable, timestamps; unique `(from, to, type, since_year)` **NULLS NOT DISTINCT** — идемпотентный upsert при `since_year IS NULL`; `from <> to`).

**Дисциплины:** `canon_disciplines`, `canon_clan_disciplines` (PK `clan_id, discipline_id`, `is_in_clan`), `canon_discipline_powers` (уровень 1–9, unique `(discipline_id, level, name)`).

**Поколения:** `canon_generations` — PK `generation` (3–15), `max_blood_pool`, `blood_per_turn`, `max_trait_rating`, `notes`.

**Пути:** `canon_paths` (`is_humanity`, `virtue_conscience`, `virtue_selfcontrol`), справочник шкалы `canon_path_sin_levels` (уровни 1–10), `canon_path_sins` (PK `path_id, level` → текст греха).

**Лист V20:** `canon_merits_flaws`, `canon_backgrounds`, `canon_attributes`, `canon_abilities`.

**Лор для RAG:** `canon_lore_entries` (`slug` string 128, `title`, `text`, `category` indexed, `tags` jsonb nullable, источники, `era_*` smallint), `canon_lore_entry_entities` (`entity_type` string 32, `entity_id`, unique на triplet, timestamps; ссылки на `canon_sects` / `canon_clans` по slug из front matter `entities`), производные `canon_lore_chunks` (`embedding` vector 1024, HNSW). Категории — enum `LoreCategory` в приложении. Файлы — только `resources/canon/lore/` (без вложенных каталогов по category).

## Каркас мира и игры

```mermaid
erDiagram
  users ||--o| characters : "PC user_id UNIQUE"
  chronicles ||--o{ game_sessions : has
  chronicles ||--o{ world_entities : contains
  game_sessions ||--o{ scenes : has
  scenes ||--o{ messages : contains
  scenes ||--o| scene_contexts : canon
  scenes ||--o{ scene_participants : who_is_here
  world_entities ||--o| characters : "shared PK"
  world_entities ||--o| locations : "shared PK"
  world_entities ||--o| factions : "shared PK"
  world_entities ||--o| clans : "shared PK"
  world_entities ||--o| coteries : "shared PK"
  world_entities ||--o| circles : "shared PK"
  world_entities ||--o| others : "shared PK"
  world_entities ||--o| items : "shared PK"
  world_entities ||--o| concepts : "shared PK"
  world_entities ||--o| world_events : "shared PK"
  world_entities ||--o{ world_entity_aliases : names
  world_entities ||--o{ world_relations : graph
```

## Лист персонажа

Один пользователь = один PC (`characters.user_id` unique). Гули — отдельные `characters` с `character_type=ghoul` и `domitor_character_id` на вампира (PC или NPC). `user_id` у гуля пуст: доступ игрока идёт через домитора.

Сир (`sire_character_id`) — Объятья. Домитор гуля — не сир.

Рейтинг листа (сила 3) — `character_stats`. Текущие ресурсы сессии (кровь, временная воля) — `character_status` с журналом и `revision`.

```mermaid
erDiagram
  world_entities ||--o| characters : identity
  users ||--o| characters : "PC only"
  characters ||--o{ characters : "domitor of ghouls"
  characters ||--o{ character_stats : ratings
  character_stats ||--o{ character_stat_specializations : specs
  characters ||--o{ character_disciplines : known
  disciplines ||--o{ character_disciplines : level
  disciplines ||--o{ discipline_powers : catalog
  character_disciplines ||--o{ character_powers : learned
  characters ||--o| character_status : now
  characters ||--o{ character_status_effects : modifiers
  characters ||--o{ character_status_changes : journal
  characters ||--o{ character_health_boxes : v20_health
  characters ||--o{ character_merits_flaws : merits
  characters ||--o| character_biographies : canon
  characters ||--o{ character_biography_versions : snapshots
  characters }o--o| clans : clan_entity_id
  clans }o--o| factions : sect_faction_id
  coteries }o--o| factions : sect_faction_id
  circles }o--o| factions : sect_faction_id
  characters }o--o| characters : sire
```

`character_stats.category`: `attribute`, `ability`, `background`, `virtue`, `other`. Unique `(character_id, category, stat_key)`.

`characters.experience` — одно неотрицательное число (начисление и трата).

`character_status`: `temporary_willpower`, `blood_pool`, `hunger` (колонка V5, на листе V20 не показывать и не писать через sheet API), `health_state`, `fatigue`, `current_location_id`, `revision`. Канон здоровья на листе — 7 строк `character_health_boxes` (`box_index` 0–6, `damage` bashing/lethal/aggravated). SPA заполняет клетки префиксом слева направо; `health_state` выводится из худшей заполненной клетки (torpor с листа не ставится).

`character_merits_flaws`: `kind` merit/flaw, `name`, `cost`, optional `note`. Eloquent-модель задаёт `$table = 'character_merits_flaws'`.

## Игра: сессии, сцены, сообщения

```mermaid
erDiagram
  chronicles ||--o{ game_sessions : has
  users ||--o{ game_sessions : created_by
  game_sessions ||--o{ scenes : has
  scenes ||--o| scene_contexts : one
  scenes ||--o{ scene_participants : many
  scenes ||--o{ messages : many
  characters ||--o{ scene_participants : enters
  characters ||--o{ messages : author_character_id
  users ||--o{ messages : operator
  messages }o--o| copilot_requests : one_use
  locations ||--o| scene_contexts : location
```

Одна active `game_session` на хронику. Сообщения канон диалога; `user_id` — оператор (nullable после удаления аккаунта), `author_character_id` — кто говорит в мире.

## Граф мира

```mermaid
erDiagram
  world_relation_types ||--o{ world_relations : typed
  world_entities ||--o{ world_relations : source
  world_entities ||--o{ world_relations : target
  world_relations ||--o| character_relationships : "character to character"
  world_relations ||--o| character_affiliations : "character to world"
  character_affiliations ||--o{ character_affiliation_changes : journal
  world_events ||--o{ world_event_participants : who
  world_events ||--o{ world_event_sources : provenance
```

`controls` в каталоге — character/faction → location/faction, не вампир→гуль. Свита хранится в `domitor_character_id`.

## Лор, правила, знание

```mermaid
erDiagram
  chronicles ||--o{ lore_entries : has
  lore_entries ||--o{ lore_entry_versions : snapshots
  lore_entries ||--o{ lore_entry_entities : mentions
  world_entities ||--o{ lore_entry_entities : mentioned
  characters ||--o{ character_lore_knowledge : exceptions
  lore_entries ||--o{ character_lore_knowledge : grant_or_deny
  rulesets ||--o{ rule_documents : contains
  rule_documents ||--o{ rule_document_versions : snapshots
  chronicles ||--o{ chronicle_rule_overrides : house_rules
  characters ||--o{ character_rule_knowledge : grants
  rule_documents ||--o{ character_rule_knowledge : known
```

NPC видит статью, если `classification` ∈ `lore_clearance_levels` (smallint[]), плюс grant, минус deny; `situational` — только grant. `visibility` лора ≠ знание NPC. Собственная биография доступна без уровня статьи.

## Память персонажа

```mermaid
erDiagram
  characters ||--o{ character_memory_nodes : owns
  character_memory_nodes ||--o{ character_memory_edges : associates
  character_memory_nodes ||--o{ memory_node_entities : bridge
  character_memory_nodes ||--o{ memory_node_messages : bridge
  character_memory_nodes ||--o{ memory_node_events : bridge
  character_memory_nodes ||--o{ memory_node_lore_entries : bridge
  character_memory_nodes ||--o{ memory_node_scenes : bridge
```

Текст воспоминания не становится каноном мира. Мосты только явные.

## Производные индексы (не канон)

- `character_bio_chunks` ← биография
- `lore_chunks` ← лор
- `game_rule_chunks` ← правила
- `message_embeddings` ← сообщения
- `character_memory_nodes.embedding` + `search_vector`

Таблицы `rag_chunks` нет.

## Очередь экстрактора

`extraction_runs` — аудит прогонов экстрактора. Поля: `chronicle_id`, `source_type` (`lore` \| `biography` \| `scene`), `source_id`, `status` / `trigger` / окно `from_message_id`–`to_message_id` (сцена), nullable `from_char_offset`–`to_char_offset` (лор), `driver`, `model`, `raw_response` (jsonb), `candidates` (jsonb), `user_id`. Лор: курсор следующего окна — max `to_char_offset` у `reviewed` runs; supersede `needs_review` только того же char-окна. Лор/био: ошибочный вызов модели строку не создаёт. Сцена: run пишется сразу; parse/таймаут → `failed`. Курсор `scenes.last_extracted_to_message_id` двигается только после успешного окна. Accept в канон — лор/сцена (mentions, relations, events), био (memories). См. [[API/Extract]], [[Project/Extractor]].

## UI и backlog

HTTP-лист V20: [[API/Characters]], [[Features/Characters]]. Blood Per Turn нет: кровь — число `character_status.blood_pool`. Справочник мира: [[API/World]], [[Features/World]]. Лор: [[API/Lore]].

Ещё нет экранов (таблицы уже есть, пустые маршруты не добавлять):

- CRUD памяти в SPA.
