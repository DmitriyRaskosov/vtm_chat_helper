# Plan: Граф локаций (Location Nodes)

## 1. Backend (Laravel)

### 1.1 База данных и Модели
*   **Миграция:** `location_nodes` (`chronicle_location_id` FK → `chronicle_locations`, cascade; parent_id, name, description, embedding vector(1024)).
*   **Сцена:** nullable `chronicle_location_id` на `scene_contexts` (ссылка на шаблон, узлы не копируются).
*   **Модель:** `LocationNode` ↔ `ChronicleLocation`; HNSW на `embedding`.

### 1.2 Парсинг и API
*   `LocationNodeParserService`: LLM JSON Mode → flatten → **`embedBatch`** → replace nodes для шаблона.
*   `LocationNodeController`: `GET/POST parse/PATCH name/DELETE` под `/api/locations/{chronicleLocation}/location-nodes` (без ручного create).

### 1.3 Context Engineering
*   `EventGeneratorService`: `chronicle_location_id` из контекста сцены; при наличии узлов — General + Specific (pgvector); иначе fallback — сырой `environment_details`.

## 2. Frontend (Vue 3)

### 2.1 Мастерская (WorkshopView.vue)
*   Редактирование шаблона локации: «Разобрать на объекты», дерево (`LocationTree.vue`) — переименование и удаление.

## 3. Риски
*   LLM может вернуть кривой JSON (try-catch, доменная ошибка).
*   Очень большие деревья — лимит токенов парсера (out of scope: разбиение).
