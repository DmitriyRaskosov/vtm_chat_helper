# Plan: Граф локаций (Location Nodes)

## 1. Backend (Laravel)

### 1.1 База данных и Модели
*   **Миграция:** `location_nodes` (`chronicle_location_id` FK → `chronicle_locations`, cascade; parent_id, name, description, embedding vector(1024)).
*   **Сцена:** nullable `chronicle_location_id` на `scene_contexts` (ссылка на шаблон, узлы не копируются).
*   **Модель:** `LocationNode` ↔ `ChronicleLocation`; HNSW на `embedding`.

### 1.2 Парсинг и API
*   `LocationNodeParserService`: LLM JSON Mode → flatten → **`embedBatch`** → replace nodes для шаблона.
*   Промпт: `resources/prompts/scene_parser/system.md`.
*   `LocationNodeController`: `GET/POST parse/PATCH name/DELETE` под `/api/locations/{chronicleLocation}/location-nodes` (без ручного create).

### 1.3 Context Engineering
*   `EventGeneratorService`: `chronicle_location_id` из контекста сцены.
*   Если у шаблона есть узлы: General (корень + прямые дети, L1) + Specific (топ-3 по pgvector).
*   Если узлов нет или шаблон не привязан: fallback — сырой `environment_details`.

## 2. Frontend (Vue 3)

### 2.1 Мастерская (WorkshopView.vue)
*   Редактирование шаблона локации: «Разобрать на объекты», дерево (`LocationTree.vue`) — переименование и удаление.
*   Composable `useLocationNodes.js` (parse, rename, delete по `chronicleLocationId`). Ручного добавления узла нет.

## 3. Риски
*   LLM может вернуть кривой JSON (try-catch, доменная ошибка).
*   Очень большие деревья — лимит токенов парсера (out of scope: разбиение).
*   **Вынесено из критериев приёмки:** таблица `location_nodes` (id, chronicle_location_id, parent_id, name, description, embedding). Узлы принадлежат шаблону `chronicle_locations`, не сцене. Сцена хранит только `chronicle_location_id` и не копирует узлы. Парсинг — JSON Mode и `embedBatch`. Поиск для генератора — General (корень + L1) и Specific (топ-3 pgvector); иначе сырой `environment_details`.
