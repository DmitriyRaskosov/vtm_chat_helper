# RAG API

Контроллер: `RagSearchController`. Слои: `app/Rag/`.

## GET /api/rag/search

**Auth:** sanctum + middleware `storyteller`

**Query:**

| Параметр | Тип | Правила |
|----------|-----|---------|
| `q` | string | required, max 2000 |
| `limit` | int | optional, 1–20, default 5 |
| `types` | array | optional, значения: `message`, `summary`, `lore` |

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
- Каждый L0/L1/final/session summary индексируется как `summary`; metadata содержит уровень, session/scene и покрытый диапазон message ID
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

`ContextBuilder` отдельно ищет `summary` и `message`; lore в Copilot пока не используется. Scope пассивного поиска пока не изменён и остаётся глобальным. Copilot tools (`search_messages`, `search_summaries`) фильтруют по `game_session_id`. Запланирован отдельный этап с явными профилями `active_scene`, `game_session` и `global` для пассивного RAG.

См. [[Architecture/Backend]], [[API/Copilot]].
