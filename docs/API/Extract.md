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
| `reparse` | boolean | optional; только с `lore_entry_id` — разобрать статью **с начала** (supersede всех `reviewed` / `needs_review` / `failed` runs этой статьи, окно с offset 0) |

Нельзя передавать два или три источника вместе (**422**).

### Срез лора (`lore_entry_id`)

- Не больше `EXTRACTOR_LORE_ARTICLE_MAX_CHARS` (default **10000**) символов `canonical_text` за один POST.
- Статья длиннее — char-окна по 10k; курсор по `reviewed` runs (`to_char_offset`); в run пишутся `from_char_offset` / `to_char_offset`.
- Повторный POST supersede-ит `needs_review` только **того же окна**.
- Когда весь текст уже разобран (`reviewed` до конца), обычный POST → **422** «no text left»; `reparse: true` — полный переразбор с offset 0.

**Response 201:** `{ "extraction_run_id", "run": { … } }` — см. ниже.

Успешный прогон сохраняется в `extraction_runs`. Лор/био: ошибка модели или сломанный JSON — **не** создаёт строку. Сцена: run создаётся сразу (`running`); parse/таймаут/502 → `failed` (курсор не двигается). Сломанный JSON (502): сырой текст — `storage/extractor-fails/extractor-parse-fail-*.txt` (на хосте, без `docker cp`); в `laravel.log` только `extractor.parse_failed` (`bytes`, `json_error`, `file`). В API тело модели не отдаётся.

### Срез сцены (`scene_id`)

Ручная кнопка «Разобрать» — **синхронный** POST (как лор). Авторазбор — фоновая job после накопления полного окна или при `PATCH /scenes/{id}/close` (хвост &lt; лимита).

- Окна **не пересекаются**: курсор `scenes.last_extracted_to_message_id`; следующее окно с `id` после курсора.
- Размер: до `EXTRACTOR_SCENE_MESSAGE_LIMIT` (default **30**) и до профильного бюджета: вход ≤ `EXTRACTOR_SCENE_INPUT_TOKENS` (8192), лента ≤ `EXTRACTOR_SCENE_FEED_TOKENS` (5392); effective `num_predict` ≥ 512.
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

**Response 200:** `{ "enabled": true, "lore": { "article_max_chars", "characters_per_token" }, "scene": { "message_limit", "input_tokens", "feed_tokens" } }` — `enabled: false`, если `EXTRACTOR_DRIVER=none`.

Optional query `lore_entry_id` (+ optional `chronicle_id`): добавляет `lore_window` — `{ from_char_offset, to_char_offset, total_chars, window_index, window_count, can_extract }` для кнопок «Разобрать» / «Разобрать заново» в UI.

## GET /api/extract/inbox

**Auth:** sanctum + storyteller

Inbox рассказчика: прогоны **лора, биографии и сцен** со статусом `needs_review` или `failed`. Когда pending-кандидатов не осталось, run → `reviewed` и из inbox исчезает. Повторный POST «Разобрать» для того же лора supersede-ит старые `needs_review` **того же char-окна** (`from_char_offset`–`to_char_offset`); второе окно длинной статьи не трогает первое. Failed — чтобы переразобрать сломанное окно; бейдж «Разбор» считает оба.

Query: optional `chronicle_id`, `status` (один статус вместо дефолта), `count_only=true` (только `{ "count": N }`).

**Response 200:** `{ "count", "runs": [ { …run, "source_label", "scene_title"?, "message_count"? } ] }`

## POST /api/extract/{run}/reparse

**Auth:** sanctum + storyteller · `source_type=scene` или `lore`

**Сцена:** синхронный повтор того же `from_message_id`–`to_message_id`. Старый run → `superseded` (+ `superseded_by_run_id`); pending старого run accept/discard **422**. Успех двигает курсор, если он ещё позади `to_message_id`.

**Лор:** синхронный повтор того же char-окна (`from_char_offset`–`to_char_offset`). Старый run → `superseded`. Полный переразбор всей статьи — `POST /api/extract` с `reparse: true`.

**Response 201:** как POST `/api/extract`.

## GET /api/extract/{run}

**Auth:** sanctum + storyteller · optional `chronicle_id` · **Response 200:** `{ "run": { … } }`

## PATCH /api/extract/{run}/candidates/{index}

**Auth:** sanctum + storyteller

**Body:** `candidate_type=mention` и хотя бы одно из:

| Поле | Правила |
|------|---------|
| `name` | непустая строка |
| `kind` | `faction` \| `clan` \| `coterie` \| `circle` \| `other` \| `location` \| `item` \| `concept` |
| `sect_faction_id` | только для `kind` clan/coterie/circle; активная `faction` той же хроники, или `null` снять |
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

- **Лор / сцена — mention:** directory `new_entity` → `WorldEntityService::create` с optional `sect_faction_id` и `aliases` (aka); `alias_of_entity_id` → `addAka` для extra aliases (имя mention не дублируется, если совпадает с каноном target), статус `merged`; совпадение с алиасом → `accepted` без `create`; `character`/`event` new_entity → **422**. Коллизия alias → **422**.
- **Лор / сцена — relation:** `WorldRelationService::relate`; дубликат → `merged`; концы — matched, accepted или merged mention в том же прогоне; невалидные endpoints → `discarded` + `discard_reason` на матчинге.
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
| 502 | сломанный JSON от модели (…); **лимит генерации** (`done_reason: length`, текст про `EXTRACTOR_LORE_OUTPUT_TOKENS` / `EXTRACTOR_SCENE_OUTPUT_TOKENS` по профилю; run не сохраняется) |

## Конфиг

`config/extractor.php`: профили `lore` / `scene` / `biography`, `EXTRACTOR_CHARACTERS_PER_TOKEN=2`, `prompt_version=graph-extract-v2`. Отдельный биндинг `ExtractorChatProvider` (`think: false`, timeout 300 с). `num_ctx` — общий `OLLAMA_CONTEXT_LENGTH` (16384). См. [[Development/Environment]].
