# Backend

Laravel JSON API. Маршруты только в `routes/api.php`.

## Слои

### `app/Rag/`

| Компонент | Назначение |
|-----------|------------|
| `RagIndexer` | Индексация сообщений и лора в `rag_chunks` |
| `RagSearcher` | Cosine nearest neighbors в pgvector |
| `EmbeddingProvider` | `ollama` (Ollama) или `stub` (тесты) |

### `app/Llm/`

| Компонент | Назначение |
|-----------|------------|
| `ChatProvider` | Интерфейс чат-модели |
| `OllamaChatProvider` | Ollama `/api/chat` для `qwen3:8b` |
| `NpcCopilotService` | Сборка промпта, вызов LLM, парсинг JSON drafts |

### Jobs

- `IndexRagMessageJob` — после `POST /api/messages` (`RAG_INDEX_SYNC` sync vs queue)

## Модели

- `Message` — канон чата; nullable `npc_name` для реплик НПС
- `RagChunk` — `source_type` (`message`, `lore`), `embedding`, metadata

## Middleware

- `auth:sanctum` — защищённые маршруты
- `storyteller` (`EnsureStoryteller`) — только рассказчик

## Конфиг

`config/rag.php`, `config/ollama.php`, `config/copilot.php`.

## Artisan

- `rag:embed-ping`, `llm:ping` — smoke Ollama
- `rag:index-lore` — ручная индексация чанка лора

## API

См. [[API/Overview]].
