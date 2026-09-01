# Structure

Карта репозитория game-chat (Laravel 13 + Vue 3).

Два приложения: JSON API в Docker (Sail) и Vue SPA в `frontend/`. Браузер: http://localhost:5173 (Vite на хосте). API: http://localhost:8080/api.

## Корень

| Файл / каталог | Назначение |
|----------------|------------|
| `composer.json`, `compose.yaml` | PHP 8.4, PostgreSQL+pgvector, Redis, Mailpit, Ollama |
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
| `Message.php` | scene_id, body, npc_name, token estimate |
| `RagChunk.php` | векторный индекс для RAG |

### Context/

- `TokenEstimator.php` — версионируемая локальная оценка токенов
- `MessageWindow.php`, `MessageWindowSelector.php` — целые окна будущей L0-суммаризации

### Rag/

- `RagIndexer.php` — индексация сообщений и лора
- `RagSearcher.php` — поиск по pgvector
- `OllamaEmbeddingProvider.php` — эмбеддинги через Ollama

### Llm/

- `OllamaChatProvider.php` — `qwen3:8b`
- `NpcCopilotService.php` — сборка промпта + черновики

### Jobs

- `IndexRagMessageJob.php` — индексация после сообщения

### Enums

- `RagSourceType.php` — `message`, `lore` (в MVP); `npc`, `relationship` — задел для этапа 4

Слои: [[Architecture/Backend]].

## database/

### migrations/

- `game_sessions`, `scenes` — доменная иерархия чата
- `messages` — user_id, scene_id, body, npc_name, token estimate
- `rag_chunks` — embedding `vector(1024)`, HNSW index

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

Рассказчик → `POST /api/copilot/drafts` → последние сообщения + RAG (`qwen3-embedding:0.6b`) → Ollama `qwen3:8b` → JSON с черновиками.

Рассказчик правит → `POST /api/messages` `{ body, npc_name }` → чат для всех, `author` = имя НПС, RAG-индекс сообщения.

Подробнее: [[Features/Copilot]], [[API/Copilot]].

Ollama только внутри Docker: `http://ollama:11434` (порт 11434 не на хосте).
