# Карточка клана, дисциплины, regards

**Статус:** в работе (2026-09-17). Живой пошаговый план хроники — эта заметка. Файл в `.cursor/plans/` каноном не является.

Экстрактор (этапы 1–4) — [[Project/Extractor]]. Парсер профиля клана — **после** этого плана, секция «После» там же. Не этап архива [[Archive/Architecture Migration]].

Канон текста остаётся статьёй лора ([[Architecture/Lore]]). Этот план даёт **куда писать** снимок клана руками (и позже парсером). Сцена, диаблери-событие и правки промпта экстрактора **не** входят в работу.

```mermaid
flowchart LR
  lore[Статья_лора]
  card[clans_поля]
  cat[disciplines_каталог]
  pivot[clan_disciplines]
  graph[world_relations_regards]
  sheet[character_disciplines]
  lore -.->|позже_парсер| card
  lore -.->|позже_парсер| pivot
  lore -.->|позже_парсер| graph
  cat --> pivot
  cat --> sheet
  card --> graph
```

## Согласованные правила

- Один узел крови: «Отступники Бруха» = **aka** «Бруха», не второй клан. `sect_faction_id` = типичный дом в справочнике, не запрет персонажу быть в другой секте (лист уже разделяет `clan_entity_id` и секту).
- Дисциплины — каталог `disciplines`, не `world_entities`. Клановые ссылки ≠ изученное на листе. Диаблери позже только пишет `character_disciplines`.
- Стереотипы — ключ **`regards`** в `world_relations` (направленный, `note` = цитата). Не `hostile_to`, не `character_relationships`, не `character_affiliations`.
- Убежище / организация / chargen / книги остаются в статье. На карточке только `motto`, `weakness`, `appearance`.

## 1. Каталог дисциплин и pivot

Сид v20 без части клановых (`database/migrations/2026_09_12_000002_add_character_sheet_fields.php`: animalism…protean).

Новая миграция:

- INSERT недостающих ключей `v20`: `obtenebration`, `vicissitude`, `thaumaturgy`, `necromancy`, `chimerstry`, `serpentis`, `quietus`, `dementation` (тот же стиль `key` / английский `display_name`).
- Таблица `clan_disciplines`: `clan_id` → `clans.id`, `discipline_id` → `disciplines.id`, unique `(clan_id, discipline_id)`, **без level**.

Модель `Clan` `belongsToMany` Discipline; `Discipline` — обратная связь. Сервис синхрона набора ids рядом с `WorldEntityService` (не плодить второй граф).

## 2. Поля карточки на `clans`

Колонки nullable `text`: `motto`, `weakness`, `appearance`. Запись в `insertClan` / `update` typed, как `sect_faction_id`.

HTTP `WorldEntityController::serialize`: для `clan` отдавать три поля + `discipline_ids`. POST/PUT: те же поля только если `entity_type=clan` (иначе 422). Form Requests: `StoreWorldEntityRequest`, `UpdateWorldEntityRequest`.

Список дисциплин для UI — уже `GET /api/character-sheet/catalog` (`disciplines`). Новый ST-only endpoint не нужен.

## 3. Ключ `regards`

`WorldRelationTypeCatalog`: INSERT-строка (старые миграции каталога не переписывать):

- source: `clan`
- target: `clan`, `faction`, `other`
- `symmetric: false`, без `inverse_key`
- цитата в существующем `world_relations.note`

`WorldDirectoryRelationController::DIRECTORY_KEYS` и `StoreWorldDirectoryRelationRequest`: добавить `regards`. В store прокинуть optional `note` в `WorldRelationService::relate` (поле в serialize уже есть, в POST сейчас не принимается). Дубликат пары+ключа — как у остальных directory-рёбер. `hostile_to` между кланами по-прежнему 422.

Не менять GraphRAG CTE: исходящие `regards` от Ласомбра подхватятся сами.

## 4. UI справочника

`useWorldEntities.js`, `WorldDirectoryTab.vue`, `useWorldDirectoryRelations.js`:

- при типе клан: motto / weakness / appearance; чекбоксы дисциплин из catalog;
- блок «Отношение к»: цель (клан / фракция / other) + цитата; синхрон `regards` после create/save по образцу `part_of`;
- query keys: `controls,owns,part_of,regards`.

Дерево сект не перестраивать. Подпись секты в форме — «типичная секта» в docs, без смены API. Brief: [[Agent/ui-world]]. Контракт: [[API/World]]. Фича: [[Features/World]].

## 5. Тесты и docs (в той же задаче, что код)

- `WorldDirectoryTest` (или узкий feature): POST клана с тремя дисциплинами и motto; PUT меняет набор; не-клану `discipline_ids` → 422.
- `WorldRelationTypeTest` / `WorldRelationTest`: `regards` Ласомбра→Бруха, одна строка, `neighbors(Бруха)` пусто; `hostile_to` клан→клан 422.
- `CharacterDisciplineTest`: персонаж клана A ставит дисциплину не из `clan_disciplines` (диаблери) — клановый pivot не меняется.
- Каталог: новые ключи есть после migrate.

Docs: [[API/World]], [[API/Overview]] если надо, [[Architecture/World]], [[Architecture/Database]] (одно предложение про `clan_disciplines` / колонки, без ERD-простыни), [[Agent/ui-world]], [[Features/World]], [[Development/Testing]], [[Project/Roadmap]].

## Приёмка

- Карточка Ласомбра: motto, слабость, внешность, три клановые дисциплины из каталога.
- `regards` на Бруха с цитатой; обратного ребра нет.
- Лист НПС может взять чужую дисциплину, не меняя `clan_disciplines`.
- Промпт экстрактора не менялся.

## После (не этот план)

Парсер профиля клана — backlog в [[Project/Extractor]] («После: профиль клана»). Код парсера не писать, пока карточка и `regards` не в каноне.
