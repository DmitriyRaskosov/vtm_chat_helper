# Tasks: Граф локаций (Location Nodes)

## Этап 1: Backend (БД и Модели)
- [x] Миграция `location_nodes` (`chronicle_location_id`, parent_id, name, description, embedding).
- [x] `chronicle_location_id` на `scene_contexts` (nullable FK).
- [x] Модель `LocationNode` / связи `ChronicleLocation` ↔ `locationNodes`.
- [x] HNSW-индекс для `embedding`.

## Этап 2: Backend (TDD - Парсинг)
- [x] `LocationNodeParserTest` (дерево, `embedBatch`).
- [x] Промпт `resources/prompts/scene_parser/system.md`.
- [x] `LocationNodeParserService` (LLM JSON, `embedBatch`, сохранение по шаблону).

## Этап 3: Backend (TDD - API и Retrieval)
- [x] `EventGeneratorLocationTest` (General + Specific; fallback без узлов).
- [x] `EventGeneratorService` (узлы шаблона сцены; fallback → `environment_details`).
- [x] API `/locations/{chronicleLocation}/location-nodes` (index, parse, PATCH name, delete).
- [x] `EventGeneratorApiTest` (fallback с сырым текстом окружения).

## Этап 4: Frontend (UI - Мастерская)
- [x] `useLocationNodes.js` (parse, rename, delete по `chronicleLocationId`).
- [x] `LocationTree.vue` (просмотр, переименование, удаление).
- [x] `WorkshopView.vue` (без черновой сцены; кнопка «Разобрать на объекты»).

## Этап 5: Финализация (DoD)
- [x] Полный прогон тестов, Pint, PHPStan, сборка фронта (Docker).
- [x] Статусы в `spec.md` и `tasks.md`.
