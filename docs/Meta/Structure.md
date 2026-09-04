# Structure

Карта репозитория game-chat (Laravel 13 + Vue 3).

Два приложения: JSON API в Docker (Sail) и Vue SPA в `frontend/`. Браузер: http://localhost:5173 (Vite на хосте). API: http://localhost:8080/api.

## Корень

| Файл / каталог | Назначение |
|----------------|------------|
| `composer.json`, `compose.yaml` | PHP 8.4 API + queue worker, PostgreSQL+pgvector, Redis, Mailpit, Ollama |
| `.env`, `.env.example` | порты, RAG, Ollama |
| `sail.cmd` | Sail под Windows |
| `artisan` | CLI (через `docker compose exec laravel.test`) |
| `README.md` | быстрый старт |
| `docs/` | Obsidian vault ([[Home]]) |

## frontend/ — Vue 3 SPA

| Путь | Назначение |
|------|------------|
| `src/views/ChatView.vue` | чат + панель рассказчика (copilot) |
| `src/auth.js` | axios + Sanctum token |
| `vite.config.js` | прокси `/api` → localhost:8080 |

Запуск: `cd frontend && npm run dev` (порт 5173, не в Docker). Подробнее: [[Architecture/Frontend]].

## app/ — backend

### Http/Controllers/

| Файл | Маршрут |
|------|---------|
| `ChatController.php` | GET/POST `/api/messages`, scoped по сцене |
| `GameSessionController.php` | active/create игровых сессий |
| `SceneController.php` | create/activate/close сцен |
| `CopilotController.php` | POST `/api/copilot/drafts` (storyteller) |
| `RagSearchController.php` | GET `/api/rag/search` (storyteller) |
| `Auth/*` | register, login, logout, `/api/user` |

### Http/Middleware

- `EnsureStoryteller.php` — middleware `storyteller`

### Models/

| Модель | Поля / роль |
|--------|-------------|
| `User.php` | login, role (storyteller \| player) |
| `GameSession.php` | игровая сессия |
| `Scene.php` | сцена и её lifecycle |
| `Message.php` | scene_id, body, npc_name, token estimate, nullable copilot_request_id |
| `RagChunk.php` | векторный индекс для RAG |
| `CopilotRequest.php` | prompt, drafts, context metadata и выбранный результат |
| `ContextSummary.php` | immutable L0/L1/scene_final/session memory |
| `StorytellerIntentSummary.php` | rolling meta-память промптов рассказчика |
| `ContextSummarySource.php` | ordered provenance на messages/child summaries |
| `SceneContextState.php` | курсоры L0 и L1 |

### Context/

- `TokenEstimator.php` — версионируемая локальная оценка токенов
- `MessageWindow.php`, `MessageWindowSelector.php` — целые окна будущей L0-суммаризации
- `ContextBuilder.php`, `ContextBuild.php` — бюджетированный prompt и provenance включённых источников
- `SummaryGenerator.php`, `GeneratedSummary.php` — структурированная генерация
- `SummaryManager.php` — L0/L1/final/session orchestration
- `StorytellerIntentGenerator.php`, `StorytellerIntentManager.php` — rolling memory промптов Copilot

### Retrieval/

- `RetrievalOrchestrator.php`, `RetrievalScope.php` — session-scoped tool calls
- `Tools/SearchMessagesTool.php`, `GetMessageRangeTool.php`, `SearchSummariesTool.php`

### Rag/

- `RagIndexer.php` — индексация сообщений и лора
- `RagSearcher.php` — поиск по pgvector
- `OllamaEmbeddingProvider.php` — эмбеддинги через Ollama

### Llm/

- `OllamaChatProvider.php` — `qwen3:8b`, `chatTurn` и tools
- `NpcCopilotService.php`, `CopilotDraftResult.php` — вызов/парсинг черновиков и результат для аудита

### Jobs

- `IndexRagMessageJob.php` — индексация после сообщения
- `IndexRagSummaryJob.php` — retryable индексация summaries
- `SummarizeSceneWindowJob.php` — фоновая L0/L1 суммаризация
- `FinalizeSceneContextJob.php` — final scene и session summary
- `RefreshStorytellerIntentJob.php` — rolling intent после Copilot

### Enums

- `RagSourceType.php` — `message`, `lore` (в MVP); `npc`, `relationship` — задел для этапа 4

Слои: [[Architecture/Backend]].

## database/

### migrations/

- `game_sessions`, `scenes` — доменная иерархия чата
- `messages` — user_id, scene_id, body, npc_name, token estimate
- `rag_chunks` — embedding `vector(1024)`, HNSW index
- `copilot_requests` — успешные генерации, drafts, версии и context metadata; `messages.copilot_request_id` — одноразовая связь
- `storyteller_intent_summaries` — immutable rolling summaries намерений рассказчика
- `context_summaries`, `context_summary_sources`, `scene_context_states` — иерархическая память и provenance

### factories/

- `UserFactory` — `storyteller()`, default password `password`
- `MessageFactory`

## routes/

- `api.php` — единственные HTTP-маршруты (без `web.php` / Blade)

## config/

- `rag.php`, `ollama.php`, `copilot.php`, `context.php`

## tests/Feature/

- `AuthenticationTest`, `ChatTest`, `GameSessionSceneTest`, `RagSearchTest`, `CopilotTest`

См. [[Development/Testing]].

## Copilot flow

Рассказчик → `POST /api/copilot/drafts` → `ContextBuilder` (raw + RAG + intent, бюджет 12000) → Ollama `qwen3:8b` (tools, `num_ctx=16384`) → сохранённый request + JSON с черновиками.

Рассказчик правит → `POST /api/messages` `{ body, npc_name, copilot_request_id, copilot_draft_index }` → одноразовая связь → чат для всех и RAG-индекс сообщения.

Подробнее: [[Features/Copilot]], [[API/Copilot]].

Ollama только внутри Docker: `http://ollama:11434` (порт 11434 не на хосте).
