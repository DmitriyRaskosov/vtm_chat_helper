# Extract API

Контроллер: `ExtractController`. Сервис: `GraphExtractorService` (`app/Extractor/`).

Экстрактор читает **срез** (статья лора, биография персонажа или лента сцены) и компактный каталог активных `world_entities` хроники. Модель возвращает JSON-кандидатов; PHP сопоставляет имена через `AliasNormalizer` и `WorldEntityService::findByAlias`. В канон (`world_entities`, `world_relations`, `world_events`, …) пишется только через accept.

См. [[Project/Extractor]], [[Architecture/World]].

## POST /api/extract

**Auth:** sanctum + middleware `storyteller`

**Body:**

| Поле | Тип | Правила |
|------|-----|---------|
| `lore_entry_id` | integer | ровно один из трёх источников |
| `character_id` | integer | биография персонажа той же хроники |
| `scene_id` | integer | сцена той же хроники; непустой хвост после курсора |
| `from_message_id` / `to_message_id` | integer | optional; явное окно сцены (оба, contiguous ids) |
| `chronicle_id` | integer | optional; как у world/lore (`Chronicle::resolveId`) |

Нельзя передавать два или три источника вместе (**422**).

**Response 201:** `{ "extraction_run_id", "run": { … } }` — см. ниже.

Успешный прогон сохраняется в `extraction_runs`. Лор/био: ошибка модели или сломанный JSON — **не** создаёт строку. Сцена: run создаётся сразу (`running`); parse/таймаут/502 → `failed` (курсор не двигается). Сломанный JSON (502): сырой текст — `storage/extractor-fails/extractor-parse-fail-*.txt` (на хосте, без `docker cp`); в `laravel.log` только `extractor.parse_failed` (`bytes`, `json_error`, `file`). В API тело модели не отдаётся.

### Срез сцены (`scene_id`)

Ручная кнопка «Разобрать» — **синхронный** POST (как лор). Авторазбор — фоновая job после накопления полного окна или при `PATCH /scenes/{id}/close` (хвост &lt; лимита).

- Окна **не пересекаются**: курсор `scenes.last_extracted_to_message_id`; следующее окно с `id` после курсора.
- Размер: до `EXTRACTOR_SCENE_MESSAGE_LIMIT` (default **30**) и до токен-бюджета (`TokenEstimator` + `OLLAMA_CONTEXT_LENGTH` − prompt − 256; effective `num_predict` ≥ 512).
- Участники: `имя | тип` (character_type).
- Сообщения по `id` возрастания: `id | author | body` (тела не копируются в `extraction_runs`).
- Каталог сущностей хроники + enabled-ключи рёбер.
- Пустой хвост — **422**.

Прогон сцены в `extraction_runs`: `status` (`queued` \| `running` \| `needs_review` \| `reviewed` \| `superseded` \| `failed`), `trigger` (`auto` \| `manual` \| `reparse`), `from_message_id`, `to_message_id`. Успех LLM → `needs_review`; курсор `scenes.last_extracted_to_message_id` двигается вперёд до `to_message_id` (в том числе после успешного reparse failed-окна). Parse/502/таймаут → `failed` (run сохраняется, кандидатов нет; курсор не двигается). Авто-job **не** ставит то же окно повторно, пока есть `queued` / `running` / `needs_review` / `failed`; ручной POST и reparse — повтор.

### Кандидаты после PHP

- Имя совпало с алиасом → `matched_entity_id`, `status: pending`.
- Не совпало → `candidate_type: new_entity` (без INSERT).
- Relation с неизвестным `key` → `discarded` (`invalid_key`).
- **Лор:** `events` / `memories` → `discarded` (`unsupported_for_source`).
- **Био:** только `memories`; `mentions`, `relations`, `events` → `discarded`.
- **Сцена:** `events`, `relations`, `mentions` pending; `memories` → `discarded`. `events[]`: `title`, `summary`, `participants[]` (имя, optional `role`, `matched_entity_id`), `source_message_ids[]` (только id этой сцены).

## GET /api/extract/status

**Auth:** sanctum + storyteller

**Response 200:** `{ "enabled": true }` — `false`, если `EXTRACTOR_DRIVER=none`.

## GET /api/extract/inbox

**Auth:** sanctum + storyteller

Inbox рассказчика: прогоны **лора, биографии и сцен** со статусом `needs_review` или `failed`. Failed — чтобы переразобрать сломанное окно; бейдж «Разбор» считает оба.

Query: optional `chronicle_id`, `status` (один статус вместо дефолта), `count_only=true` (только `{ "count": N }`).

**Response 200:** `{ "count", "runs": [ { …run, "source_label", "scene_title"?, "message_count"? } ] }`

## POST /api/extract/{run}/reparse

**Auth:** sanctum + storyteller · только `source_type=scene`

Синхронный повтор того же `from_message_id`–`to_message_id`. Старый run → `superseded` (+ `superseded_by_run_id`); pending старого run accept/discard **422**. Успех двигает курсор, если он ещё позади `to_message_id`.

**Response 201:** как POST `/api/extract`.

## GET /api/extract/{run}

**Auth:** sanctum + storyteller · optional `chronicle_id` · **Response 200:** `{ "run": { … } }`

## PATCH /api/extract/{run}/candidates/{index}

**Auth:** sanctum + storyteller

**Body:** `candidate_type=mention` и хотя бы одно из:

| Поле | Правила |
|------|---------|
| `name` | непустая строка |
| `kind` | `faction` \| `location` \| `item` \| `concept` |
| `subtype` | подтип справочника для текущего `kind` (`sect`/`clan`/`coterie`/`circle`/`other` у фракции; иначе enum места/предмета/идеи); `null` или `""` снять |
| `aliases` | массив строк — дополнительные aka (не канон); пустой массив очищает список на кандидате |
| `alias_of_entity_id` | активная сущность той же хроники («это имя уже существующего узла»), или `null` снять привязку |

Кандидат должен быть `pending` (**422** иначе). `kind=character` / `kind=event` в теле — **422** (валидация).

После PATCH PHP пересчитывает матчинг этого mention (`findByAlias` или `alias_of_entity_id`) и флаги `endpoints_resolved` у pending-relations того же run. Остальные pending не сбрасываются.

**Response 200:** `{ "run": { … } }`

## POST /api/extract/{run}/candidates/{index}/accept

**Auth:** sanctum + storyteller

**Body:** `candidate_type` = `mention` | `relation` | `memory` | `event`; optional `chronicle_id`.

`index` — стабильный индекс в `candidates.mentions`, `candidates.relations`, `candidates.memories` или `candidates.events`.

**Поведение:**

- **Лор / сцена — mention:** directory `new_entity` → `WorldEntityService::create` с `subtype` (без него фракция — `other`) и `aliases` (aka); `alias_of_entity_id` → `WorldEntityService::addAka` для имени mention и каждого extra alias, статус `merged` (вторая сущность не создаётся); совпадение с алиасом → `accepted` без `create`; `character`/`event` new_entity → **422** на лоре и сцене (событие только через `event`). Коллизия `(chronicle_id, normalized_alias)` при aka → **422**.
- **Лор / сцена — relation:** `WorldRelationService::relate`; дубликат → `merged`; концы — matched или accepted в том же прогоне (включая accepted event по title).
- **Био — memory:** `CharacterMemoryService::remember`.
- **Сцена — event:** `WorldEntityService::create(Event)` → participants (matched) → sources (`scene` + `message_id` из среза) → `WorldEventService::approve`; `status: accepted`, `created_entity_id`, `world_event_id`.
- **Сцена — memory accept:** **422**.

**Response 200:** `{ "run": { … } }`

## POST /api/extract/{run}/candidates/{index}/discard

Как accept по `candidate_type`; канон не меняется.

## Ошибки

| Код | Условие |
|-----|---------|
| 403 | player |
| 404 | источник или run другой хроники |
| 422 | archived lore; несколько источников; пустая сцена; срез не влезает в окно 16384 (мало места под `num_predict`); кандидат не pending; accept relation без концов; memory на сцене |
| 503 | `EXTRACTOR_DRIVER=none`; Ollama недоступна; **таймаут** экстрактора (`EXTRACTOR_HTTP_TIMEOUT_SECONDS`, текст про timeout, не «unavailable») |
| 502 | сломанный JSON от модели (после нормализации переносов и лишней кавычки перед ключом — `" "name"` / `""name"` — JSON всё ещё битый; run нет; сырьё в `storage/extractor-fails/`); **лимит генерации** (`done_reason: length`, текст про `EXTRACTOR_MAX_OUTPUT_TOKENS`; run не сохраняется) |

## Конфиг

`config/extractor.php`: `EXTRACTOR_DRIVER`, `EXTRACTOR_OLLAMA_MODEL`, `EXTRACTOR_TEMPERATURE`, `EXTRACTOR_MAX_OUTPUT_TOKENS` (`num_predict`, default 5000), `EXTRACTOR_HTTP_TIMEOUT_SECONDS`, `EXTRACTOR_THINK`, `EXTRACTOR_SCENE_MESSAGE_LIMIT`. Отдельный биндинг `ExtractorChatProvider` (`think: false`, timeout 300 с). `num_ctx` — общий `OLLAMA_CONTEXT_LENGTH` (16384).

См. [[Development/Environment]].
