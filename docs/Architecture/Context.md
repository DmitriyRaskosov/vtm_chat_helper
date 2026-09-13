# Контекст Copilot

Контекст Copilot собирает **Context Assembler** (`context-assembler-v1`, prompt `npc-drafts-v6`). Иерархическая суммаризация L0/L1 и память намерений рассказчика сняты. Пассивного message-RAG нет: сырой хвост сцены остаётся обязательным слоем, а лор/память/правила попадают в prompt только как заранее собранные bounded-блоки. Tool loop (`search_messages`, `get_message_range`) по-прежнему добирает **старую историю сессии**, не GraphRAG.

`App\Context\ContextBuilder` — тонкий внутренний фасад: получает уже разрешённое snapshot-имя, prompt, scene/session/storyteller IDs и обязательный для HTTP Copilot `character_id`, затем делегирует `ContextAssembler`.

## Что приходит на вход

HTTP `POST /api/copilot/drafts` → `CopilotController` резолвит активную сцену и NPC по обязательному `character_id`, читает `world_entities.canonical_name` как snapshot, затем `NpcCopilotService` вызывает билдер.

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

Дальше assembler сам читает канон PostgreSQL (не Redis, не filesystem):

1. `Scene` + `GameSession` + `Chronicle` по `scene_id`.
2. `Character` по `character_id` (shared PK с `world_entities`) должен быть NPC той же хроники, иначе ошибка. `WorldEntity` персонажа читается отдельно: обратный `belongsTo` на тот же `id` не объявляется.

Модель **не** читает БД сама. GraphRAG и SQL providers работают до вызова Ollama.

## Порядок слоёв

Фиксированный порядок user-блоков:

`system` → NPC identity → scene → status → storyteller prompt → recent messages → direct relations → biography → personal memory → world/lore → rules → closing.

В LLM уходят **два** сообщения:

- `role=system` — только секция `system`;
- `role=user` — остальные включённые секции через `\n\n`, каждая со своим `##` заголовком (closing без заголовка).

Пустые секции опускаются целиком.

## Провайдеры: источник, форма, подготовка

У каждого provider в `config/context.php` → `assembler.sections`: `min`/`max` токенов, `priority`, `required`, политика truncation. Оценка токенов — тот же `TokenEstimator` (Unicode / 3).

Обязательные секции **нельзя вытеснить GraphRAG**. Сначала резервируются system, identity, scene, status, prompt, closing; затем newest-first целые сообщения сцены; остаток бюджета — relations → bio → memory GraphRAG → world/lore → rules. Если остаток 0, GraphRAG **не вызывается**.

### system

- **Откуда:** шаблон `SystemPromptProvider` + флаг `COPILOT_TOOLS_ENABLED`.
- **Вид:** инструкции «напиши N in-character drafts для NPC X», JSON schema, запрет выдумывать неизвестный лор/память/правила, опционально разрешение tools.
- **Для LLM:** целиком в `messages[0]`. Не режется.

### npc_identity

- **Откуда:** `npcName`; при `character_id` — `characters` (type, clan через `world_entities`, generation, nature/demeanor/concept), `CharacterSheetReader` (строки `character_stats` + активные специализации), `character_disciplines`.
- **Вид:** текстовые строки `NPC: …`, `Type: npc`, `Clan: …`, `attribute Сила: 3/5`, `Discipline: Dominate 3`.
- **Для LLM:** блок `## NPC identity`. Хвост статов отбрасывается при превышении max (250).

### scene

- **Откуда:** `scenes.title/description/status`, `game_sessions.title`, `chronicles.title/setting`, `scene_contexts` (location canonical name, atmosphere, situation, `storyteller_notes`), текущие `scene_participants`.
- **Вид:** короткие поля сцены и хроники, затем `Location` / `Atmosphere` / `Situation` / `Storyteller notes` / `Present: Name (npc, visible)`.
- **Для LLM:** `## Scene`. При нехватке max сначала режется хвост (description и далее). Закрытая сцена остаётся читаемой: assembler берёт frozen snapshot. Copilot HTTP по-прежнему требует активную сцену.

### status

- **Откуда:** `character_status` (hunger, blood_pool, temporary_willpower, health_state, fatigue, `current_location_id` → имя из `world_entities`), активные `character_status_effects`.
- **Вид:** `Hunger: 2`, `Health: injured`, `Effect (temporary): …`.
- **Для LLM:** `## Status`. Нет персонажа или нет строки status — секция опущена. Лишние эффекты отбрасываются с хвоста.

### storyteller_prompt

- **Откуда:** body `prompt`.
- **Вид:** как ввёл рассказчик.
- **Для LLM:** `## Storyteller prompt`. Не режется (лимит HTTP 2000 символов).

### recent_messages

- **Откуда:** `messages` этой сцены, `COPILOT_HISTORY_LIMIT` (30), newest-first набор **целых** сообщений, пока влезают в общий бюджет. Автор: `author_character_id` → canonical name, иначе `npc_name`, иначе `users.name`.
- **Вид:** `Имя: текст` в хронологическом порядке.
- **Для LLM:** `## Recent chat`. Сообщение никогда не режется посередине: не влезло целиком — отбрасывается самое старое. Старая история той же сессии — только через tools **после** этого prompt.

### direct_relations

- **Откуда:** 1-hop `WorldRelationService::neighbors` (исходящие + входящие symmetric), метрики `character_affiliations` по shared PK ребра, входящие `character_relationships` даже для направленных типов. Не GraphRAG.
- **Вид:** `Виктория member_of Камарилья (weight 1.00) [stance allied, loyalty 4, …]`.
- **Для LLM:** `## Direct relations`. Лишние рёбра отбрасываются (сначала более слабые по `|weight|`).

### biography

- **Откуда:** каноническая строка `character_biographies` (summary, principles, motivation, fears, desires, behavioral_rules, full_text). **Не** `character_bio_chunks` / векторный поиск — воспроизводимый канон.
- **Вид:** `Summary: …`, затем остальные поля; `Full text` в конце, его режут первым.
- **Для LLM:** `## Biography`. Своя биография доступна NPC без knowledge grant.

### memory_graph (optional GraphRAG)

- **Откуда:** `MemoryGraphRag::expand(character, prompt)` — seed `CharacterMemorySearcher` (vector+FTS+alias), CTE по `character_memory_edges` depth ≤2, min `|weight|`, hard caps из `config/retrieval.php`.
- **Вид:** bundle узлов (текст, type, depth, seed, `is_false_belief`) и рёбер. False belief помечается `[false belief]`.
- **Для LLM:** `## Personal memory`. Сначала узлы по score, затем рёбра между уже включёнными. Не влезло — drop lowest score. Вызов пропускается, если обязательные слои съели бюджет. Чтение не меняет recall/traversal.

### world_lore (optional GraphRAG)

- **Откуда:** `WorldGraphRag::expandForNpc(character, prompt, scene)` — seed NPC + события сцены + exact alias из prompt + lore chunks с grant + memory bridges; CTE `world_relations`; после каждого перехода storyteller-only events скрыты, lore только по `character_lore_knowledge`. Карточка самого NPC в этот блок не дублируется (она уже в identity).
- **Вид:** сущности, рёбра, public canonical events, affiliation one-liners, `Known lore: {chunk}`.
- **Для LLM:** `## World`. Публичный лор без grant **не** попадает. Truncation — с хвоста набранных строк.

### rules (optional)

- **Откуда:** grants `character_rule_knowledge` → `rule_documents.ruleset_id` → `RuleSearcher::searchForCharacter` (корпус `game_rule_chunks`, chronicle override). Без grant секция пустая, поиск не зовётся. Хроника своего `ruleset_id` не имеет.
- **Вид:** текст чанка + `source_reference`.
- **Для LLM:** `## Rules`.

### closing

- **Откуда:** шаблон.
- **Вид:** `Generate N distinct reply drafts for {npcName}.`
- **Для LLM:** последняя строка user-сообщения.

## Бюджет

Глобальный вход — `CONTEXT_COPILOT_MAX_INPUT_TOKENS` (12000). `num_ctx=16384`, `num_predict=3000`. Сумма оценок system+user не превышает бюджет; иначе обязательный prompt не собирается (`InvalidArgumentException`).

Per-section max в `config/context.php` ограничивает **внутреннюю** нарезку слоя. Итоговый `input_token_estimate` считается по уже склеенным LLM messages (склейка `\n\n` может чуть расходиться с суммой `section_token_counts`).

## Provenance и аудит

`copilot_requests.context_metadata` после успешной генерации:

- версии: `builder_version`, `prompt_version`, `token_estimator_version`;
- бюджет и `input_token_estimate`;
- `chronicle_id`, `character_id`, `scene_id`, `game_session_id`, `storyteller_id`;
- `included_raw_message_ids`, `excluded_raw_message_count`;
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

Для `qwen3:8b`: `num_ctx=16384`, `num_predict=3000`, максимум 12000 оценочных токенов входа. Запас ~1384 на шаблон чата и погрешность оценки.

См. [[Architecture/Retrieval]], [[Architecture/Memory]], [[Architecture/World]], [[Architecture/Lore]], [[Architecture/Rules]], [[API/Copilot]].
