# Контекст Copilot

Контекст Copilot собирает **Context Assembler** (`context-assembler-v2`). Один HTTP `POST /api/copilot/drafts` даёт **два** запроса к Ollama подряд (не сложение бюджетов в один `num_ctx`):

1. **Топики** (`npc-topics-v1`) — промпт ST + короткий хвост сцены + компактная identity этого NPC. Без био, лора, графа, правил, status и relations. Вход ≤ `CONTEXT_TOPIC_MAX_INPUT_TOKENS` (8000), `num_predict` ≈ 384.
2. **Поиск без LLM** — векторы и уже записанный граф. Запрос GraphRAG/правил — распарсенные топики (`ContextRequest::retrievalQuery()`), не сырой промпт. Лор — допуском этапа 1 (`classification` ∈ `lore_clearance_levels`, плюс grant, минус deny; `situational` — только grant).
3. **Реплика** (`npc-drafts-v7`) — те же слои канона, что раньше, с разметкой речь/канон/память. Вход ≤ `CONTEXT_COPILOT_MAX_INPUT_TOKENS` (12000), `num_predict=3000`. Tools истории только здесь.

Иерархическая суммаризация L0/L1 и память намерений рассказчика сняты. Пассивного message-RAG нет. Tool loop (`search_messages`, `get_message_range`) добирает **старую историю сессии**, не GraphRAG и не «диалоги с этим NPC».

`App\Context\ContextBuilder` — фасад: `buildTopics` / `buildReply`. `build()` без топиков — reply-путь для тестов assembler (GraphRAG тогда ищет по промпту ST).

## Что приходит на вход

HTTP `POST /api/copilot/drafts` → `CopilotController` резолвит активную сцену и NPC по обязательному `character_id`, читает `world_entities.canonical_name` как snapshot, затем `NpcCopilotService` вызывает два прохода билдера.

Assembler получает DTO `ContextRequest`:

| Поле | Откуда | Вид |
|------|--------|-----|
| `npcName` | `world_entities.canonical_name` по `character_id` | строка snapshot для обращения в prompt и аудита |
| `prompt` | body `prompt` | сырой текст рассказчика, max 2000 символов |
| `sceneId` | body `scene_id` или активная сцена хроники | int, только активная сцена |
| `draftCount` | `config('copilot.draft_count')` | обычно 3 |
| `storytellerId` | Sanctum user | int, для аудита |
| `gameSessionId` | `scenes.game_session_id` | int |
| `characterId` | body `character_id` | обязательный NPC той же хроники |
| `pass` | `topics` или `reply` | какой проход собирается |
| `searchTopics` | JSON топиков после первого вызова | список коротких фраз; пусто на проходе топиков |
| `historyLimit` | `COPILOT_TOPIC_HISTORY_LIMIT` (8) или `COPILOT_HISTORY_LIMIT` (30) | лимит хвоста сцены |
| `compactIdentity` | true на топиках | без статов и дисциплин |

Дальше assembler сам читает канон PostgreSQL (не Redis, не filesystem):

1. `Scene` + `GameSession` + `Chronicle` по `scene_id`.
2. `Character` по `character_id` (shared PK с `world_entities`) должен быть NPC той же хроники, иначе ошибка. `WorldEntity` персонажа читается отдельно: обратный `belongsTo` на тот же `id` не объявляется.

Модель **не** читает БД сама. GraphRAG и SQL providers работают между двумя LLM-вызовами, на проходе реплики.

## Порядок слоёв

### Топики

`system` → компактная NPC identity → scene → storyteller prompt → короткий хвост сцены → closing. Optional-слои (status, relations, bio, memory, world, rules) **не вызываются** (`not_in_pass`).

### Реплика

`system` → NPC identity → scene → status → storyteller prompt → recent messages → direct relations → biography → personal memory → world/lore → rules → closing.

В LLM уходят **два** сообщения на каждый проход:

- `role=system` — только секция `system`;
- `role=user` — остальные включённые секции через `\n\n`, каждая со своим `##` заголовком (closing без заголовка).

Пустые секции опускаются целиком.

## Провайдеры: источник, форма, подготовка

У каждого provider в `config/context.php` → `assembler.sections`: `min`/`max` токенов, `priority`, `required`, политика truncation. Оценка токенов — тот же `TokenEstimator` (Unicode / 3).

На проходе реплики обязательные секции **нельзя вытеснить GraphRAG**. Сначала резервируются system, identity, scene, status, prompt, closing; затем newest-first целые сообщения сцены; остаток бюджета — relations → bio → memory GraphRAG → world/lore → rules. Если остаток 0, GraphRAG **не вызывается**. На топиках leftover-цикл не идёт.

### system

- **Топики:** извлечь 3–8 коротких поисковых фраз для **этого** NPC из промпта ST и речи сцены. JSON `{"topics":[…]}`. Без tools и drafts.
- **Реплика:** шаблон `SystemPromptProvider` + флаг `COPILOT_TOOLS_ENABLED`. Инструкции «напиши N in-character drafts», JSON schema, подписи `[speech]` / `[canon]` / `[memory]` / `[sheet]` / `[rules]`, запрет выдумывать неизвестный лор/память/правила.
- **Для LLM:** целиком в `messages[0]`. Не режется.

### npc_identity

- **Откуда:** `npcName`; при `character_id` — `characters` (type, clan через `world_entities`, generation, nature/demeanor/concept). На реплике ещё `CharacterSheetReader` (статы + специализации) и `character_disciplines`. На топиках статы и дисциплины пропускаются.
- **Вид:** текстовые строки `NPC: …`, `Type: npc`, `Clan: …`, на реплике `attribute Сила: 3/5`, `Discipline: Dominate 3`.
- **Для LLM:** блок `## NPC identity`. Хвост статов отбрасывается при превышении max (250).

### scene

- **Откуда:** `scenes.title/description/status`, `game_sessions.title`, `chronicles.title/setting`, `scene_contexts` (location canonical name, atmosphere, situation, `storyteller_notes`), текущие `scene_participants`.
- **Вид:** короткие поля сцены и хроники, затем `Location` / `Atmosphere` / `Situation` / `Storyteller notes` / `Present: Name (npc, visible)`.
- **Для LLM:** `## Scene`. При нехватке max сначала режется хвост (description и далее). Закрытая сцена остаётся читаемой: assembler берёт frozen snapshot. Copilot HTTP по-прежнему требует активную сцену.

### status

- **Откуда:** `character_status` (hunger, blood_pool, temporary_willpower, health_state, fatigue, `current_location_id` → имя из `world_entities`), активные `character_status_effects`.
- **Вид:** `Hunger: 2`, `Health: injured`, `Effect (temporary): …`.
- **Для LLM:** `## Status`. Нет персонажа или нет строки status — секция опущена. На топиках секция не собирается. Лишние эффекты отбрасываются с хвоста.

### storyteller_prompt

- **Откуда:** body `prompt`.
- **Вид:** как ввёл рассказчик.
- **Для LLM:** `## Storyteller prompt`. Не режется (лимит HTTP 2000 символов). При нехватке бюджета режут сообщения, не этот блок.

### recent_messages

- **Откуда:** `messages` этой сцены. Топики — `COPILOT_TOPIC_HISTORY_LIMIT` (8); реплика — `COPILOT_HISTORY_LIMIT` (30). Newest-first набор **целых** сообщений, пока влезают в бюджет прохода. Автор: `author_character_id` → canonical name, иначе `npc_name`, иначе `users.name`.
- **Вид:** `[speech] Имя: текст` в хронологическом порядке.
- **Для LLM:** `## Scene speech`. Сообщение никогда не режется посередине: не влезло целиком — отбрасывается самое старое. Старая история той же сессии — только через tools **на проходе реплики**.

### direct_relations

- **Откуда:** 1-hop `WorldRelationService::neighbors` (исходящие + входящие symmetric), метрики `character_affiliations` по shared PK ребра, входящие `character_relationships` даже для направленных типов. Не GraphRAG. Только реплика.
- **Вид:** `[canon] Виктория member_of Камарилья (weight 1.00) [stance allied, loyalty 4, …]`.
- **Для LLM:** `## Direct relations`. Лишние рёбра отбрасываются (сначала более слабые по `|weight|`).

### biography

- **Откуда:** каноническая строка `character_biographies` (summary, principles, motivation, fears, desires, behavioral_rules, full_text). **Не** `character_bio_chunks` / векторный поиск — воспроизводимый канон. Только реплика.
- **Вид:** `[sheet] Summary: …`, затем остальные поля; `Full text` в конце, его режут первым.
- **Для LLM:** `## Biography`. Своя биография доступна NPC без knowledge grant.

### memory_graph (optional GraphRAG, только реплика)

- **Откуда:** `MemoryGraphRag::expand(character, retrievalQuery())` — seed `CharacterMemorySearcher` (vector+FTS+alias) по топикам, CTE по `character_memory_edges` depth ≤2, min `|weight|`, hard caps из `config/retrieval.php`.
- **Вид:** bundle узлов (текст, type, depth, seed, `is_false_belief`) и рёбер. Строки с префиксом `[memory]`. False belief помечается `[false belief]`.
- **Для LLM:** `## Personal memory`. Сначала узлы по score, затем рёбра между уже включёнными. Не влезло — drop lowest score. Вызов пропускается, если обязательные слои съели бюджет. Чтение не меняет recall/traversal.

### world_lore (optional GraphRAG, только реплика)

- **Откуда:** `WorldGraphRag::expandForNpc(character, retrievalQuery(), scene)` — seed NPC + события сцены + exact alias из топиков + lore chunks по допуску/исключениям + memory bridges; CTE `world_relations`; после каждого перехода storyteller-only events скрыты, лор по `CharacterLoreKnowledgeService::visibleLoreEntryIds`. Карточка самого NPC в этот блок не дублируется (она уже в identity).
- **Вид:** сущности, рёбра, public canonical events, affiliation one-liners, `Known lore: {chunk}` — все с префиксом `[canon]`.
- **Для LLM:** `## World`. Статья грифа выше допуска **не** попадает, пока нет grant-исключения. Truncation — с хвоста набранных строк.

### rules (optional, только реплика)

- **Откуда:** grants `character_rule_knowledge` → `rule_documents.ruleset_id` → `RuleSearcher::searchForCharacter` по `retrievalQuery()`. Без grant секция пустая, поиск не зовётся. Хроника своего `ruleset_id` не имеет.
- **Вид:** `[rules]` + текст чанка + `source_reference`.
- **Для LLM:** `## Rules`.

### closing

- **Топики:** `Extract search topics for {npcName}.`
- **Реплика:** `Generate N distinct reply drafts for {npcName}.`
- **Для LLM:** последняя строка user-сообщения.

## Бюджет

Два отдельных потолка входа, ключ реплики не менялся:

| Проход | Вход | Выход | Окно |
|--------|------|-------|------|
| Топики | `CONTEXT_TOPIC_MAX_INPUT_TOKENS` (8000) | `COPILOT_TOPIC_MAX_OUTPUT_TOKENS` (384), `temperature` 0.2 | `num_ctx=16384` |
| Реплика | `CONTEXT_COPILOT_MAX_INPUT_TOKENS` (12000) | `OLLAMA_MAX_OUTPUT_TOKENS` (3000) | то же |

8000+384 и 12000+3000 по отдельности влезают в 16384; складывать 8000+12000 в один запрос нельзя. Сумма оценок system+user не превышает бюджет прохода; иначе обязательный prompt не собирается (`InvalidArgumentException`).

Per-section max в `config/context.php` ограничивает **внутреннюю** нарезку слоя. Итоговый `input_token_estimate` считается по уже склеенным LLM messages (склейка `\n\n` может чуть расходиться с суммой `section_token_counts`).

Если JSON топиков не разобрать, поиск идёт по промпту ST, как раньше.

## Provenance и аудит

`copilot_requests.context_metadata` после успешной генерации — **проход реплики** на верхнем уровне:

- версии: `builder_version`, `prompt_version` (`npc-drafts-v7`), `token_estimator_version`;
- `pass`, `search_topics`, `topics`;
- бюджет и `input_token_estimate` реплики (12000 / фактическая оценка);
- `topic_pass`: бюджет 8000, оценка входа, `history_limit` 8, `num_predict` топиков, included message ids топиков;
- `chronicle_id`, `character_id`, `scene_id`, `game_session_id`, `storyteller_id`;
- `included_raw_message_ids`, `excluded_raw_message_count` реплики;
- `section_token_counts` и `sections.{key}.{included,tokens,truncation,elapsed_ms,provenance}` — IDs messages/stats/effects/relations/nodes/edges/lore chunks/rule chunks, `data_versions.status_revision` / `biography_version` / `context_revision`, GraphRAG filters;
- tool-loop дописывает `NpcCopilotService` (`tool_invocations`, …).

Нет `included_summary_ids` / пассивных rag chunk ids. Prompt воспроизводим: те же канонические строки и те же ID в metadata.

Старые строки `messages`/`copilot_requests` с одним `npc_name` остаются читаемым snapshot-аудитом, но новые HTTP-запросы по имени не принимаются.

## Оценка токенов

`App\Context\TokenEstimator`:

```text
max(1, ceil(число Unicode-символов / CONTEXT_CHARACTERS_PER_TOKEN))
```

По умолчанию 3 символа на токен. Версия `unicode-chars-v1-cpt3`. Это оценка, не tokenizer модели.

## Окно Ollama

Для `qwen3:8b`: `num_ctx=16384` на оба вызова. Топики: вход 8000 + выход 384. Реплика: вход 12000 + выход 3000 (запас ~1384 на шаблон чата и погрешность оценки).

См. [[Architecture/Retrieval]], [[Architecture/Memory]], [[Architecture/World]], [[Architecture/Lore]], [[Architecture/Rules]], [[API/Copilot]].
