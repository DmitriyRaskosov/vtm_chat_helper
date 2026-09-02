# Backend

Laravel JSON API. Маршруты только в `routes/api.php`.

## Слои

### `app/Rag/`

| Компонент | Назначение |
|-----------|------------|
| `RagIndexer` | Индексация сообщений, summaries и лора в `rag_chunks` |
| `RagSearcher` | Cosine nearest neighbors в pgvector |
| `EmbeddingProvider` | `ollama` (Ollama) или `stub` (тесты) |

### `app/Llm/`

| Компонент | Назначение |
|-----------|------------|
| `ChatProvider` | Интерфейс чат-модели |
| `OllamaChatProvider` | Ollama `/api/chat` для `qwen3:8b`, runtime-лимиты контекста и ответа |
| `NpcCopilotService` | Вызов LLM и парсинг JSON drafts |
| `CopilotDraftResult` | Drafts и данные для аудита успешной генерации |

### `app/Context/`

| Компонент | Назначение |
|-----------|------------|
| `TokenEstimator` | Версионируемая локальная оценка токенов |
| `MessageWindowSelector` | Выбор целых сообщений для будущего L0 |
| `MessageWindow` | Результат выбора: сообщения, токены, oversized |
| `ContextBuilder` | Бюджетированная сборка prompt из raw history и RAG |
| `ContextBuild` | LLM messages и metadata включённых источников |
| `SummaryGenerator` | Структурированная генерация summary через `qwen3:8b` |
| `SummaryManager` | L0/L1/final/session orchestration, курсоры и provenance |
| `GeneratedSummary` | Narrative и структурированная metadata результата |

### Jobs

- `IndexRagMessageJob` — после `POST /api/messages` (`RAG_INDEX_SYNC` sync vs queue)
- `IndexRagSummaryJob` — независимая retryable индексация сохранённого summary
- `SummarizeSceneWindowJob` — проверка и свёртка готовых L0-окон
- `FinalizeSceneContextJob` — остаточный L0, final scene и session summary после закрытия

## Модели

- `GameSession` — игровая сессия; глобально активна не более одной
- `Scene` — сцена со статусом `draft`, `active` или `closed`
- `Message` — канон чата; обязательный `scene_id`, nullable `npc_name`, кеш оценки токенов
- `RagChunk` — `source_type` (`message`, `summary`, `lore`), `embedding`, metadata
- `CopilotRequest` — успешный вызов Copilot, drafts, context metadata и связь с выбранным сообщением
- `ContextSummary` — immutable L0/L1/scene_final/session summary
- `ContextSummarySource` — ordered provenance на message или child summary
- `SceneContextState` — L0/L1 cursors сцены

## Middleware

- `auth:sanctum` — защищённые маршруты
- `storyteller` (`EnsureStoryteller`) — только рассказчик

## Конфиг

`config/rag.php`, `config/ollama.php`, `config/copilot.php`, `config/context.php`.

## Artisan

- `rag:embed-ping`, `llm:ping` — smoke Ollama
- `rag:index-lore` — ручная индексация чанка лора
- `rag:reindex-messages` — полная переиндексация сообщений в RAG

## API

См. [[API/Overview]].
