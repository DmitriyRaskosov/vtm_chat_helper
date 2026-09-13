# RAG API

Контроллер: `RagSearchController`. Слои: `app/Rag/`.

## GET /api/rag/search

**Auth:** sanctum + middleware `storyteller`

**Query:**

| Параметр | Тип | Правила |
|----------|-----|---------|
| `q` | string | required, max 2000 |
| `limit` | int | optional, 1–20, default 5 |
| `chronicle_id` | int | required, существующая хроника |
| `game_session_id` | int | optional, дополнительный scope |
| `scene_id` | int | optional, дополнительный scope |

**Response 200:**

```json
{
  "results": [
    {
      "id": 1,
      "source_type": "message",
      "source_id": "42",
      "content": "…",
      "distance": 0.12
    }
  ]
}
```

## Индексация

- После каждого `POST /api/messages` `IndexRagMessageJob` вызывает `RagIndexer::indexMessage` (sync или queue по `RAG_INDEX_SYNC`) и upsert-ит `message_embeddings`.
- Канон остаётся в `messages`; `message_embeddings` — производный индекс с `chronicle_id`, `game_session_id`, `scene_id`.
- Лор индексируется только в `lore_chunks` (`LoreIndexer`/`LoreSearcher`), биография — в `character_bio_chunks`, правила — в `game_rule_chunks`, память — в `character_memory_nodes`.
- HTTP endpoint намеренно ищет только сообщения. Lore/rules/memory попадают в Copilot через scoped providers и knowledge grants, а не через глобальный поиск.
- Legacy `rag_chunks`, `RagSearcher`, `rag:index-lore` и source types удалены этапом 35.

## Модели Ollama

| Модель | Назначение |
|--------|------------|
| `qwen3-embedding:0.6b` | Эмбеддинги (1024-d), `OllamaEmbeddingProvider` |
| `qwen3:8b` | Генерация черновиков, не RAG |

Не путать: эмбеддинги не «отвечают» в чат; чат-модель не меняет канон.

## Переиндексация

После смены `RAG_EMBEDDING_MODEL` или `RAG_EMBEDDING_DIMENSIONS` подготовьте миграцию размерности отдельных vector-колонок и переиндексируйте сообщения:

```bash
php artisan rag:reindex-messages
```

Лор, правила и биографии перестраиваются их специализированными indexer-сервисами.

## Copilot

`ContextAssembler` не делает пассивный message-RAG. Copilot tools (`search_messages`) фильтруют по `chronicle_id` и `game_session_id` через `message_embeddings`. GraphRAG памяти и мира собирается в prompt до LLM; hybrid coordinator в Copilot loop не входит. См. [[Architecture/Context]].

См. [[Architecture/Backend]], [[API/Copilot]].
