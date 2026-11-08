# Plan: Keyword-triggered RAG по лору

## 1. Backend (Laravel)
### 1.1 База данных
*   Миграция `canon_lore_chunks`: id, canon_lore_entry_id (FK), header (string), content (text), embedding (vector(1024)), token_estimate (int).
*   HNSW-индекс для `embedding`.

### 1.2 Команда нарезки (`CanonChunkLoreCommand`)
*   Читает `.md` файлы из `resources/canon/lore/`.
*   Парсит по `##` (заголовки). Для каждого заголовка считает токены.
*   Если > 340, режет по `\n\n` (абзацы).
*   Использует `EmbeddingProvider::embedBatch` для генерации векторов.
*   Сохраняет в `canon_lore_chunks`, предварительно очистив старые для этой статьи.

### 1.3 Context Provider (`LoreRagProvider`)
*   Собирает текст: `$request->prompt` + последние 15 сообщений (из `RecentMessagesProvider` или напрямую).
*   Ищет в тексте `canonical_name` из `world_entities` (и алиасы).
*   Если найдено совпадение, получает `canon_lore_entry_id` через связующую таблицу `canon_lore_entry_entities`.
*   Делает embedding для текста (промпт + сообщения).
*   Ищет `ORDER BY embedding <=> ? LIMIT 2` в `canon_lore_chunks` для найденных entry_id.
*   Форматирует текст: `## Lore RAG: [Header] \n [Content]`.
*   Возвращает `ContextSection`.

### 1.4 ContextAssembler
*   Добавить `LoreRagProvider` в список провайдеров.
*   В `config/context.php` добавить секцию `lore_rag` (max: 700).

## 2. Риски
*   **N+1 запросов:** Если сущностей много, нужно делать один запрос к `world_entities` через `whereIn`.
*   **Задержка:** Генерация эмбеддинга для поиска может занять 200-300 мс. Это некритично для Copilot, но нужно следить.
*   **Алиасы:** Если алиас сущности совпадает с обычным словом (например, "Князь"), это может вызвать ложные срабатывания. В будущем можно ограничить поиск заглавными буквами.