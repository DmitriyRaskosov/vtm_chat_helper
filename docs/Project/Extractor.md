# Экстрактор графа

**Статус:** этапы 1–4 в коде (этап 4: 2026-09-14). Этап 1: драйвер Ollama, `extraction_runs`, POST/GET `/api/extract`, PHP-матчинг. Этап 2: accept/discard, кнопка «Разобрать» на статье лора. Этап 3: био → память. Этап 4: сцена → events + relations, тонкий HTTP событий. **После:** память сцены (кто что запомнил) — отдельно. Заменяет отложенный «этап 3» прицела (LLM-черновик узлов/рёбер на accept). Не этап архива [[Archive/Architecture Migration]].

Канон этого плана — эта заметка. Файл в `.cursor/plans/` каноном не является.

Модель читает **срез** (статья лора, биография, позже лента сцены) и компактный каталог уже известных имён. На выход — **кандидаты**, не INSERT. В канон — только принять / слить / отбросить, через те же сервисы, что кнопки UI: [[Architecture/World|WorldEntityService]], `WorldRelationService`, позже `WorldEventService` и [[Architecture/Memory|CharacterMemoryService]].

Биографию по умолчанию не предлагать как события мира (ложь персонажа). Готовый NER (Natasha / spaCy-ru) в таблицы — нет.

```mermaid
flowchart LR
  src[Срез_лор_био_сцена]
  llm[Экстрактор_Ollama]
  php[PHP_алиасы_whitelist]
  q[Очередь_кандидатов]
  st[Accept_ST]
  canon[Сервисы_канона]
  src --> llm --> php --> q --> st --> canon
```

[[Features/Copilot]] (топики → поиск → реплика) в эту цепь не входит и граф не пишет.

Связанное: [[Project/Roadmap]], [[Architecture/World]], [[Architecture/Lore]], [[Architecture/Memory]], [[Architecture/Context]], [[API/Copilot]].

## Три вызова

| Кто | Когда | Пишет граф |
|-----|-------|------------|
| Copilot | реплика NPC | нет |
| Экстрактор лора / био | кнопка рассказчика | нет, только очередь |
| Экстрактор сцены | фоновые окна + кнопка «Разобрать» (хвост / reparse) | нет, inbox `needs_review` |

`PATCH /scenes/{id}/close` **не ждёт** LLM, но может **enqueue** незакрытый хвост (&lt; 30 сообщений). Авторазбор — `RunSceneExtractionJob` после полного окна (30 или токен-стоп), не в `POST /api/messages`. Failed-окно автомат не ставит снова: inbox / reparse / ручной «Разобрать».

## Что переиспользовать

Не изобретать второй граф и не звать NER.

- Copilot: `ChatProvider` → `OllamaChatProvider` (**не** перепривязывать синглтон).
- Экстрактор: отдельный биндинг с тем же интерфейсом `ChatProvider` (или узкий `complete`).
- Алиасы: `AliasNormalizer`, `WorldEntityService::findByAlias`.
- Рёбра: enabled-ключи `world_relation_types` (`WorldRelationTypeCatalog`, `WorldRelationTypeValidator` на `relate()`).
- События: таблицы и `WorldEventService` есть; HTTP/SPA нет.
- Память: `CharacterMemoryService::remember()` есть; редактора в SPA нет.
- Образец аудита черновика: `copilot_requests`.

## Клиент модели

Сейчас — **локальная** Ollama (та же машина, что Copilot). По умолчанию chat-модель Copilot; отдельный `EXTRACTOR_OLLAMA_MODEL`, если позже поставите более сильную локальную без смены реплик.

Задел на смену, не текущая работа: `EXTRACTOR_DRIVER=ollama|none|openai_compat`. Внешний ключ (DeepSeek и аналоги) в этом плане не подключать. `none` — 503, кнопка скрыта. Copilot внешним API не подменять.

Вход модели: текст среза + компактный каталог `id | type | name | aliases` активных сущностей хроники. Не тащить всю хронику, био+лор+ленту одним запросом.

Низкая температура. Ответ — JSON. У экстрактора reasoning **выключен** (`think: false` в теле `/api/chat` + `/no_think` в system): qwen3 не тратит `num_predict` на отдельное поле `thinking`, JSON идёт в `content`. Если Ollama всё же вернёт пустой `content`, провайдер читает `thinking` как страховку. Префикс **Thinking... / ...done thinking.** в тексте — `ExtractionResponseParser` вырезает и берёт первый сбалансированный `{…}`. Перед decode: голые CR/LF → пробел; лишняя кавычка перед ключом (`{ " "key":` / `{ ""key":`) → обычный ключ (пустое `""` после `:` и экранированную пару `\`+`n` не трогает). Сломанный JSON → **502**, очередь пустая; сырой ответ — `storage/extractor-fails/` (в `laravel.log` только путь, `bytes`, `json_error`). `done_reason: length` → **502** с подсказкой про `EXTRACTOR_MAX_OUTPUT_TOKENS`.

Общее окно **16384** (`OLLAMA_CONTEXT_LENGTH`): вход (срез + каталог + промпт) и `num_predict` делят один `num_ctx`. Перед вызовом PHP оценивает вход (`TokenEstimator`) и передаёт `effective = min(EXTRACTOR_MAX_OUTPUT_TOKENS, 16384 − prompt − 256)`; если effective &lt; 512 — **422** без вызова Ollama. HTTP-таймаут экстрактора — 300 с (`EXTRACTOR_HTTP_TIMEOUT_SECONDS`), Copilot — 180 с.

Слабая локальная модель не повод писать FK из ответа LLM: упоминания текстом, сопоставление в PHP.

Тесты: `Http::fake` на Ollama, как в Copilot. Живой внешний API в `.env.example` не добавлять.

## Кандидат

LLM **не** ставит `entity_id`. Смысл формы (имена полей можно сжать):

```json
{
  "mentions": [{ "name": "Камарилья", "kind": "faction" }],
  "relations": [{ "source": "Виктория", "target": "Камарилья", "key": "member_of" }],
  "events": [{ "title": "…", "summary": "…", "participants": ["Виктория"] }],
  "memories": [{ "character": "Виктория", "text": "…", "node_type": "event" }]
}
```

`key` только из enabled-строк `world_relation_types`. Неизвестный ключ — не INSERT (отбросить или пометить «не из списка»).

После PHP:

- имя совпало с алиасом → `matched_entity_id`
- не совпало → кандидат `new_entity` (имя + предполагаемый тип), не `create`
- у relation оба конца — matched **или** accepted-new в том же прогоне (сначала сущность, потом ребро)

Статусы: `pending` / `accepted` / `merged` / `discarded`. Accept зовёт тот же сервис, что кнопка UI.

Источник режет виды кандидатов:

| Вход | Предлагать | Не предлагать |
|------|------------|---------------|
| Статья лора | mentions, relations | память NPC; события — не в этапе 2 |
| Биография | memory **этого** персонажа | `world_events`, канон мира «как факт» |
| Сцена | events + relations | новые статьи лора |

## Очередь

Таблица вроде `extraction_runs`: chronicle, `source_type` (`lore` / `biography` / `scene`), `source_id`, driver/model, raw JSON, `candidates` jsonb, user, timestamps. Плоские строки кандидатов не обязательны, если статусы живут в JSON.

ST-only API (набросок):

- `POST /api/extract` — прогон (`lore_entry_id` \| `character_id` \| `scene_id`)
- `GET /api/extract/{run}`
- `PATCH /api/extract/{run}/candidates/{i}` — правка pending mention (имя, kind, `alias_of_entity_id`) до accept
- `POST /api/extract/{run}/candidates/{i}/accept` и `…/discard`

Пока `EXTRACTOR_DRIVER=none` — даже POST не предлагает «попробуем Copilot-модель втихую». При `ollama` кнопка видна.

UI первого захода — не отдельный продукт: кнопка «Разобрать» на статье лора и на био листа, список pending принять/отбросить. Полноценный редактор памяти и карточки событий — не этапы 1–3. Accept события — когда появится тонкий HTTP `WorldEventService` (этап 4).

## Этапы

### 1. Клиент + очередь, без канона — **в коде**

Драйвер `ollama`, `extraction_runs`, POST/GET, PHP-матчинг алиасов, отсев ключей рёбер. Тесты: `ExtractorTest` + `Http::fake`. Docs: [[API/Extract]], [[Development/Environment]]. Copilot не трогать.

**Приёмка:** прогон сохраняется; `world_entities` / `world_relations` не растут из экстрактора, пока нет accept (этап 2).

### 2. Лор: кнопка и accept сущностей/рёбер — **в коде**

Срез = `canonical_text` статьи + каталог. Accept `new_entity` → `WorldEntityService::create`. Accept relation → `WorldRelationService::relate` (активный дубликат — `merged`). UI: кнопка «Разобрать» на вкладке лора. API: [[API/Extract]].

**Приёмка:** известное имя → ребро после accept; выдуманное → pending `new_entity`, `create` только по Accept. Рассказчик правит pending mention (именительный вручную, тип и подтип справочника, aka-список, либо «это имя уже существующего узла») через PATCH; accept с `alias_of_entity_id` пишет aka (`merged`) и extra aliases на цель, не `create`. Без subtype фракция на Accept — `other`.

### 3. Био: только память — **в коде**

Срез = био этого персонажа (`POST /api/extract` с `character_id`). Кандидаты `memories` → accept → `CharacterMemoryService::remember`. `events` / world-факты PHP отбрасывает. UI: кнопка «Разобрать» на био листа. SPA памяти нет — accept/discard на листе.

**Приёмка:** био не создаёт `world_events`.

### 4. Сцены — окна + inbox — **в коде**

Непересекающиеся окна по `scenes.last_extracted_to_message_id` (default 30 сообщений, раньше при токен-лимите). Inbox на `/world` (вкладка «Разбор»); в чате — бейдж, не панель кандидатов. Ручная «Разобрать» — sync POST; авто — job. Reparse superseded старый run. Срез = участники + диапазон id ленты. `events` + `relations`; `memories` → discarded.

Память сцены (кто что запомнил) — **после** этапа 4, отдельно: субъективно, по персонажу, не канон.

**Приёмка:** лента сцены даёт событие+рёбра в очередь; Copilot drafts по-прежнему без INSERT в граф.

## Сознательно нет

Автозапись из Copilot. Natasha / spaCy. Матрица NPC×NPC. Стриминг. Внутриигровые часы. Подмена Ollama у реплик. Разбор всей хроники одним запросом. Прод-подключение внешнего LLM в этом цикле. Синхронный LLM на `POST /api/messages` или на close.

## Открыто (мало)

Точное имя `EXTRACTOR_OLLAMA_MODEL` (дефолт = chat Copilot). Merge в UI сразу или достаточно accept/discard.
