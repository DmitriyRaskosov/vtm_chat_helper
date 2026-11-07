# Tasks: Граф локаций (Location Nodes)

**Status:** Completed

## Этап 1: Backend (БД и Модели)
- [x] Миграция `location_nodes` (`chronicle_location_id`, parent_id, name, description, embedding).
- [x] `chronicle_location_id` на `scene_contexts` (nullable FK); узлы в сцену не копируются (AC-5).
- [x] Модель `LocationNode` / связи `ChronicleLocation` ↔ `locationNodes`.
- [x] HNSW-индекс для `embedding`.

## Этап 2: Backend (TDD - Парсинг)
- [x] `LocationNodeParserTest` (дерево, `embedBatch`).
- [x] Промпт `resources/prompts/scene_parser/system.md`.
- [x] `LocationNodeParserService`: по «Разобрать на объекты» заменить дерево шаблона (AC-1).

## Этап 3: Backend (TDD - API и Retrieval)
- [x] `EventGeneratorLocationTest`: общий каркас и уместные объекты; запасной текст без узлов (AC-3, AC-4).
- [x] `EventGeneratorService`: узлы шаблона сцены; иначе сырой текст окружения.
- [x] API `/locations/{chronicleLocation}/location-nodes` (index, parse, PATCH name, delete) без ручного создания (AC-2).
- [x] `EventGeneratorApiTest` (fallback с сырым текстом окружения).

## Этап 4: Frontend (UI - Мастерская)
- [x] `useLocationNodes.js` (parse, rename, delete по `chronicleLocationId`).
- [x] `LocationTree.vue`: просмотр, переименование, удаление (AC-2).
- [x] `WorkshopView.vue`: вкладка «Локации», кнопка «Разобрать на объекты», без ручного добавления узлов.

## Этап 5: Финализация (DoD)
- [x] Полный прогон тестов, Pint, PHPStan, сборка фронта (Docker).
- [x] Статусы в `spec.md` и `tasks.md`.
