# AGENTS.md — маршрутизация по задачам

Домашний проект: Laravel 13 JSON API + Vue 3 SPA. Narrative docs: `docs/` (Obsidian).
**Не читать целиком репозиторий.**

## Обязательный порядок

1. Определи **тип задачи** (таблица UI briefs или доменная секция ниже).
2. Для UI открой `docs/Agent/<brief>.md`. Для backend — доменную секцию + указанный API/architecture файл.
3. **До первой правки** открывай только файлы из brief (обычно 3–6). Не делай обзорный grep по репозиторию.
4. UI-only: **не открывать** `docs/Meta/Structure.md`. Карта бэкенда там не нужна для правки Vue.

Общие правила кода: `.cursor/rules/`. Схема БД (не UI): `docs/Architecture/Database.md`.

## UI briefs

| Экран | Brief | До первой правки |
|-------|-------|------------------|
| Чат / сцены / copilot panel | `docs/Agent/ui-chat.md` | view + chat components + 2 composables |
| Список персонажей | `docs/Agent/ui-character-list.md` | `CharacterListView.vue` + `AppNav.vue` |
| Лист V20 | `docs/Agent/ui-character-sheet.md` | view + `useCharacterSheet.js` + нужная секция в `components/sheet/` |
| Мир / лор вкладка | `docs/Agent/ui-world.md` | view + world components + world composables |

HTTP только через `api` из `frontend/src/auth.js`. Не ходить в `app/Llm/`, `app/Context/`, `app/Lore/LoreSearcher.php` без явной регрессии API.

---

## World (backend: справочник, фракции, связи)

**Когда:** entities, faction relations, world graph, события, алиасы — PHP/API, не Vue.

| Что | Путь |
|-----|------|
| API | `docs/API/World.md`, `routes/api.php` (world/*) |
| Feature / architecture | `docs/Features/World.md`, `docs/Architecture/World.md` |
| Backend | `app/World/`, `WorldEntityController.php`, `WorldFactionRelationController.php` |
| UI | `docs/Agent/ui-world.md` |
| Тесты | `docker compose exec laravel.test php artisan test --filter=WorldEntityTest` |

**Не трогать без необходимости:** `app/Lore/`, `app/Memory/`, `app/Context/`, `app/Llm/`.

---

## Character sheet (backend: лист V20, биография, место)

**Когда:** stats, health, merits, disciplines, XP, biography, affiliations, place, ghoul — PHP/API.

| Что | Путь |
|-----|------|
| API | `docs/API/Characters.md` |
| Feature | `docs/Features/Characters.md` |
| Backend | `app/Character/`, `CharacterSheetController.php`, `app/Http/Requests/UpdateCharacter*.php` |
| UI | `docs/Agent/ui-character-sheet.md`, список — `docs/Agent/ui-character-list.md` |
| Тесты | `docker compose exec laravel.test php artisan test --filter=CharacterSheetTest` |

Таблицы: секция characters / affiliations в `docs/Architecture/Database.md`.

---

## Lore (backend: статьи, гриф, допуск)

**Когда:** lore entries, versions, entity links, classification, lore clearance, knowledge exceptions, lore RAG corpus — PHP/API.

| Что | Путь |
|-----|------|
| API / architecture | `docs/API/Lore.md`, `docs/Architecture/Lore.md` |
| Backend | `app/Lore/`, `CharacterLoreKnowledgeService.php`, `LoreEntryController.php` |
| UI лора | `docs/Agent/ui-world.md`; допуск на листе — `docs/Agent/ui-character-sheet.md` |
| Тесты | `docker compose exec laravel.test php artisan test --filter=LoreEntryTest` |

Соседи: `app/World/` (привязка к entities). `app/Memory/` — только если задача про memory↔lore.

---

## Copilot (backend: черновики NPC, prompt, контекст)

**Когда:** drafts, Ollama, token budget, context assembly (топики → поиск → реплика), retrieval tools.

| Что | Путь |
|-----|------|
| API | `docs/API/Copilot.md`, `docs/API/Messages.md`, `docs/API/Scenes.md` |
| Feature | `docs/Features/Copilot.md`, `docs/Features/Chat.md`, `docs/Features/Scenes.md` |
| Context / retrieval | `docs/Architecture/Context.md`, `app/Context/`, `config/copilot.php`; `docs/Architecture/Retrieval.md`, `app/Retrieval/` |
| LLM / HTTP | `app/Llm/NpcCopilotService.php`, `CopilotController.php`, `ChatController.php` |
| UI | `docs/Agent/ui-chat.md` |
| Тесты | `docker compose exec laravel.test php artisan test --filter=CopilotTest` |

GraphRAG в prompt (не tools): `MemoryGraphRag`, `WorldGraphRag` через `ContextAssembler` на проходе реплики.

---

## Прочее (коротко)

| Задача | Старт |
|--------|-------|
| Auth / роли | `docs/API/Auth.md`, `docs/Project/Roles.md`, `EnsureStoryteller.php` |
| RAG / embeddings | `docs/API/RAG.md`, `app/Rag/`, `config/rag.php` |
| Правила (rulebook) | `docs/Architecture/Rules.md`, `app/Rulebook/` |
| Память персонажа | `docs/Architecture/Memory.md`, `app/Memory/` |
| Новый эндпоинт | `routes/api.php` + Form Request + `tests/Feature/` + `docs/API/` |

## Запуск

- API: `docker compose exec laravel.test php artisan test --filter=ИмяТеста`
- Frontend: `cd frontend && npm run dev`
- Setup: `docs/Development/Setup.md`
