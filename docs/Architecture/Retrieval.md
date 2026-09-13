# Retrieval tools

Copilot может добрать **старую историю сессии** через внутренние PHP tools. Lore/NPC/relationship tools в registry не входят. Memory/World GraphRAG собирает [[Architecture/Context|Context Assembler]] **до** LLM; в Ollama tool-loop они не входят. Hybrid coordinator Copilot не использует.

## Tools

| Имя | Назначение | Лимит |
|-----|------------|-------|
| `search_messages` | семантический поиск сообщений текущей сессии | `COPILOT_TOOLS_SEARCH_LIMIT` (5) |
| `get_message_range` | непрерывный диапазон по message ID | `COPILOT_TOOLS_RANGE_LIMIT` (20) |

Каждый item обрезается до `COPILOT_TOOLS_MAX_ITEM_CHARACTERS` (400). Ответ tool не содержит полную историю сцены. `search_summaries` снят вместе с иерархией L0/L1. `search_messages` читает только scoped-корпус `message_embeddings`.

## Scope

`RetrievalOrchestrator` всегда привязан к текущей хронике и игровой сессии (`chronicle_id`, `game_session_id`). Опциональный `scene_id` принимается, только если сцена принадлежит этой сессии. Чужая хроника и чужая сессия недоступны.

Пассивного message-RAG нет: сырой хвост сцены обязателен. Лор/память/правила — заранее собранные блоки assembler, не произвольное чтение БД моделью. Tools — единственный способ семантически найти более старые **сообщения** сессии из Copilot.

## Корпуса

Отдельные индексы: `message_embeddings`, `character_bio_chunks`, `lore_chunks`, `game_rule_chunks`, `character_memory_nodes`. Поиск каждого корпуса требует его scope и не читает соседние таблицы. `GET /api/rag/search` — только ручной поиск сообщений с обязательным `chronicle_id`; глобального lore search нет.

## Hybrid coordinator

`App\Retrieval\Hybrid\HybridRetrievalCoordinator` маршрутизирует запрос по корпусам и 1-hop relations. Каждый `RetrievalHit` несёт corpus, reason, filters, token estimate и provenance. Дедуп: один чанк на документ; raw message отбрасывается, если memory node ссылается на него мостом. Copilot loop эти хиты не получает: assembler зовёт GraphRAG-сервисы напрямую.

## GraphRAG

Два отдельных CTE-контура, depth ≤2, cycle guard, hard limits и `statement_timeout` (`config/retrieval.php`, `RETRIEVAL_STATEMENT_TIMEOUT_MS`):

- `MemoryGraphRag` — только `character_memory_edges` выбранного персонажа
- `WorldGraphRag` — `world_relations` хроники; seed из NPC/сцены/алиасов/лора/мостов памяти; knowledge filter после каждого перехода

Подробности: [[Architecture/Memory]], [[Architecture/World]], [[Project/Architecture Migration]].

## Loop

`NpcCopilotService` передаёт Ollama JSON tools (`qwen3:8b` / Ollama `/api/chat`). Цикл ограничен `COPILOT_TOOLS_MAX_ITERATIONS` (2) и суммарной оценкой результатов `COPILOT_TOOLS_MAX_LOOP_TOKENS` (2000). Вызовы пишутся в `copilot_requests.context_metadata.tool_invocations`.

`COPILOT_TOOLS_ENABLED=false` отключает tools и оставляет однократную генерацию drafts.

## Код

`app/Retrieval/`, `app/Retrieval/Tools/`, `app/Retrieval/Hybrid/`, `ChatProvider::chatTurn()`. Тесты: `tests/Feature/RetrievalToolsTest.php`, `RetrievalCorpusTest.php`, `HybridRetrievalTest.php`, `MemoryGraphRagTest.php`, `WorldGraphRagTest.php`, `RetrievalGuardrailTest.php`, `ContextAssemblerTest.php`, tool-loop в `CopilotTest`.
