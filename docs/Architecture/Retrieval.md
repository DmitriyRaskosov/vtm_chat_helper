# Retrieval tools

Copilot может добрать недостающий контекст через внутренние PHP tools. Lore/NPC/relationship tools в registry не входят.

## Tools

| Имя | Назначение | Лимит |
|-----|------------|-------|
| `search_messages` | семантический поиск сообщений | `COPILOT_TOOLS_SEARCH_LIMIT` (5) |
| `search_summaries` | семантический поиск world summaries | тот же лимит |
| `get_message_range` | непрерывный диапазон по message ID | `COPILOT_TOOLS_RANGE_LIMIT` (20) |

Каждый item обрезается до `COPILOT_TOOLS_MAX_ITEM_CHARACTERS` (400). Ответ tool не содержит полную историю сцены.

## Scope

`RetrievalOrchestrator` всегда привязан к текущей игровой сессии (`game_session_id`). Опциональный `scene_id` принимается, только если сцена принадлежит этой сессии. Чужая сессия недоступна.

Пассивный RAG в [[Architecture/Context|Context Builder]] пока остаётся глобальным; явные профили `active_scene` / `game_session` / `global` — отдельный этап.

## Loop

`NpcCopilotService` передаёт Ollama JSON tools (`qwen3:8b` / Ollama `/api/chat`). Цикл ограничен `COPILOT_TOOLS_MAX_ITERATIONS` (2) и суммарной оценкой результатов `COPILOT_TOOLS_MAX_LOOP_TOKENS` (2000). Вызовы пишутся в `copilot_requests.context_metadata.tool_invocations`.

`COPILOT_TOOLS_ENABLED=false` отключает tools и оставляет однократную генерацию drafts.

## Код

`app/Retrieval/`, `app/Retrieval/Tools/`, `ChatProvider::chatTurn()`. Тесты: `tests/Feature/RetrievalToolsTest.php`, tool-loop в `CopilotTest`.
