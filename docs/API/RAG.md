# RAG API

Контроллер: `RagSearchController`. Слои: `app/Rag/`.

## GET /api/rag/search

**Auth:** sanctum + middleware `storyteller`

**Query:**

| Параметр | Тип | Правила |
|----------|-----|---------|
| `q` | string | required, max 2000 |
| `limit` | int | optional, 1–20, default 5 |
| `types` | array | optional, значения: `message`, `lore` |

**Response 200:**

```json
{
  "results": [
    {
      "id": 1,
      "source_type": "message",
      "source_id": 42,
      "title": null,
      "content": "…",
      "distance": 0.12
    }
  ]
}
```

## Индексация

- После каждого `POST /api/messages` — `RagIndexer::indexMessage` (sync или queue, `RAG_INDEX_SYNC`)
- Metadata message-чанка содержит `user_id`, `scene_id`, `game_session_id` и опциональный `npc_name`
- Канон в `messages`, производный слой в `rag_chunks` (`source_type`, `source_id`, `embedding`)
- Lore: `rag:index-lore` artisan / `RagIndexer::indexLore` (copilot в MVP ищет только `message`)

## Модели Ollama

| Модель | Назначение |
|--------|------------|
| `qwen3-embedding:0.6b` | Эмбеддинги (1024-d), `OllamaEmbeddingProvider` |
| `qwen3:8b` | Генерация черновиков, не RAG |

Не путать: эмбеддинги не «отвечают» в чат; чат-модель не пишет в `rag_chunks`.

## Переиндексация

После смены `RAG_EMBEDDING_MODEL` или `RAG_EMBEDDING_DIMENSIONS` выполните миграцию (очистка `rag_chunks` и смена размерности вектора), затем:

```bash
php artisan rag:reindex-messages
```

Lore-чанки переиндексируются вручную через `rag:index-lore`.

## Copilot

`NpcCopilotService` ищет по промпту рассказчика, тип `message` (не `lore` в текущем MVP). Фильтрация RAG по сцене будет добавлена вместе с Context Builder; recent history уже scoped по активной сцене.

См. [[Architecture/Backend]], [[API/Copilot]].
