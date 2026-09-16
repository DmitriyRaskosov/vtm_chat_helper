# Scope

MVP-срез — с чем работаем сейчас. Остальное **существует в коде**, но **вне фокуса**: маршруты в `routes/api.php` закомментированы, новые фичи туда не добавляем.

Канон маршрутов: `routes/api.php` (секции `MVP` / `frozen`).

## MVP (активно)

### Auth

| Метод | Путь |
|-------|------|
| POST | `/api/register` |
| POST | `/api/login` |
| GET | `/api/user` |
| POST | `/api/logout` |

### Game sessions & scenes

| Метод | Путь |
|-------|------|
| GET | `/api/game-sessions/active` |
| POST | `/api/game-sessions` (ST) |
| POST | `/api/game-sessions/{gameSession}/scenes` (ST) |
| PATCH | `/api/scenes/{scene}/activate` (ST) |
| PATCH | `/api/scenes/{scene}/close` (ST) |
| GET | `/api/scenes/{scene}/context` (ST) |
| PUT | `/api/scenes/{scene}/context` (ST) |
| GET | `/api/scenes/{scene}/participants` (ST) |
| POST | `/api/scenes/{scene}/participants` (ST) |
| PATCH | `/api/scenes/{scene}/participants/{character}` (ST) |

### Messages

| Метод | Путь |
|-------|------|
| GET | `/api/messages` |
| POST | `/api/messages` |

### Copilot

| Метод | Путь |
|-------|------|
| POST | `/api/copilot/drafts` (ST) |

### Characters (подмножество листа)

| Метод | Путь |
|-------|------|
| GET | `/api/characters` |
| GET | `/api/characters/{character}` |
| POST | `/api/characters` (ST) |
| PUT | `/api/characters/{character}/stats` |
| PATCH | `/api/characters/{character}/status` |
| PUT | `/api/characters/{character}/disciplines` |

### World

| Метод | Путь |
|-------|------|
| GET | `/api/world/entities` (ST) |
| POST | `/api/world/entities` (ST) |
| PUT | `/api/world/entities/{worldEntity}` (ST) |
| POST | `/api/world/entities/{worldEntity}/archive` (ST) |
| POST | `/api/world/entities/{worldEntity}/restore` (ST) |
| GET | `/api/world/entities/{worldEntity}/neighbors` (ST) |
| GET | `/api/world/relations` (ST) |
| POST | `/api/world/relations` (ST) |
| POST | `/api/world/relations/{worldRelation}/end` (ST) |

Политика фракций и directory-рёбра — тот же `/api/world/relations` с query `keys` (например `hostile_to,allied_with` или `controls,owns,part_of`).

### Lore

| Метод | Путь |
|-------|------|
| GET | `/api/lore` (ST) |
| POST | `/api/lore` (ST) |
| GET | `/api/lore/{loreEntry}` (ST) |
| PUT | `/api/lore/{loreEntry}` (ST) |
| POST | `/api/lore/{loreEntry}/archive` (ST) |
| POST | `/api/lore/{loreEntry}/restore` (ST) |

### Extract (базовый)

| Метод | Путь |
|-------|------|
| GET | `/api/extract/inbox` (ST) |
| POST | `/api/extract` (ST) |

---

## Заморожено (маршруты закомментированы в `api.php`)

Не удалено из репозитория. Не развиваем, не подключаем во frontend MVP. Раскомментировать — только осознанно, вне текущего среза.

### Character sheet (остальной лист)

- `GET /api/character-sheet/catalog`
- `PATCH /api/characters/{character}` (identity)
- `PUT /api/characters/{character}/health`
- `PUT /api/characters/{character}/merits`
- `PATCH /api/characters/{character}/experience`
- `PUT /api/characters/{character}/biography`
- `PUT /api/characters/{character}/place` (секта / клан / гавань)
- `POST /api/characters/{character}/archive`
- `POST /api/characters/{character}/restore`

### RAG

- `GET /api/rag/search`

### World (события)

- `POST /api/world/events` (+ participants, sources)

### Extract (полный workflow)

- `GET /api/extract/status`
- `GET /api/extract/{run}`
- `POST /api/extract/{run}/reparse`
- `PATCH /api/extract/{run}/candidates/{index}`
- `POST /api/extract/{run}/candidates/{index}/accept`
- `POST /api/extract/{run}/candidates/{index}/discard`

### Домены без HTTP в MVP (код/таблицы есть, UI и API вне среза)

- Редактор памяти персонажа (`character_memory_*`, GraphRAG памяти в copilot tools)
- Rulebook / game rules search в UI
- Streaming copilot, copilot для игроков (гули)
- Полный CRUD событий мира в SPA

---

## Архитектура графа (в рамках MVP)

Одна таблица **`world_relations`**: `source_type` / `source_id` / `target_type` / `target_id` / `relation`, метрики в `intensity` / `metadata`, время — `valid_from` / `valid_to`.

Удалены subtype-таблицы `character_relationships`, `character_affiliations`, `character_affiliation_changes` и связанные модели/сервисы. Контроллер — **`WorldRelationController`** (вместо `WorldFactionRelationController` + `WorldDirectoryRelationController`).

---

## Правила для агентов

- **Миграции:** не создавать новые файлы в `database/migrations/` без явной просьбы — править существующие create-миграции (`.cursor/rules/migrations.mdc`).
- **MVP:** правки только в активных маршрутах и их потребителях; замороженное не расширять.
- **Тесты:** прогон — только по запросу пользователя.
