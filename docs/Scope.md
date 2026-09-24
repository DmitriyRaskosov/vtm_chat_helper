# Scope

MVP-срез и то, что заморожено. Живой документ — обновлять при изменении границ.

## Что такое MVP

Чат для VtM V20 с Copilot-помощником рассказчика.
Мастер пишет синопсис → Copilot разворачивает в 3 варианта реплики NPC → мастер отправляет в чат.

**Не в MVP:** механика (кубы, статы, здоровье), RAG, extraction, память, рулбук, события мира, streaming.

## MVP — что работает (24.09.2026)

### Auth
Регистрация, логин, логаут, /user.

### Game
Хроники, сессии, сцены, участники сцены, контекст сцены.

### Chat
Сообщения (мастер, игрок, NPC), отправка от NPC через Copilot.

### Copilot
- `POST /api/copilot/drafts` — синопсис → 3 драфта.
- Провайдер: DeepSeek (`deepseek-chat`).
- Контекст: NPC (имя, клан, секта, слабость, дисциплины, биография),
  сцена, промпт мастера, последние сообщения.
- Провайдеры контекста: SystemPrompt, NpcIdentity, Scene, StorytellerPrompt,
  RecentMessages, DirectRelations, Biography, ClosingInstruction.

### Characters (подмножество листа)
Создание, чтение, идентичность, дисциплины, биография, место (клан/секта/гавань),
архивация/восстановление.

### World
`world_entities`, `world_relations`, `world_relation_types`, альясы.
CRUD + neighbors. Политика фракций и directory-рёбра — тот же `/world/relations`.

### Canon (справочники, read-only, сиды)
`canon_sects`, `canon_clans`, `canon_clan_sects`, `canon_clan_relations`,
`canon_disciplines`, `canon_clan_disciplines`, `canon_discipline_powers` (пусто),
`canon_lore_entries`, `canon_lore_entry_entities`.

30 статей лора в `resources/canon/lore/*.md`, импорт через `canon:import-lore`.

## Заморожено

Не удалено, но не развивается. Не подключать во фронт.

### Механика
- Stats, health, merits, experience, status — вырезаны из контроллеров и UI.
- `CanonDisciplinePower` — таблица есть, сидов нет, UI нет.
- `CharacterPower` — таблица есть, логики нет.

### Инфраструктура
- RAG: `LoreChunk`, `MessageEmbedding`, `RagSearchController`.
- Extraction: `Extractor/*`, `ExtractionRun`, `ExtractController`.
- Memory: `CharacterMemoryNode`, `MemoryGraphRag`.
- Rulebook: `RuleDocument*`, `Ruleset`.
- World events: `WorldEvent*`.
- Старый lore (не canon): `LoreEntry`, `LoreChunk`.

### Проводник контекста
- `WorldLoreProvider` — вырезан из `ContextAssembler` (тянул retrieval, memory, events).
  Вернуть, когда будет упрощённый вариант на `canon_lore_entries` + `world_relations`.

## Правила

- **Миграции:** создают только схему. Сиды — отдельно. FK — в `Schema::create` той таблицы, которая ссылается.
- **MVP-правки:** только активные роуты и их потребители. Замороженное не расширять.
- **Канон vs хроника:** `canon_*` — read-only, заполняется сидами. Всё остальное — данные игры.

## Пост-MVP (в порядке приоритета)

1. **Частичная транзакционность `CharacterSheetController::store`.**
   Сейчас при падении после создания `world_entity` остаётся orphaned запись + занятый alias.

2. **`WorldEntityAlias`: partial unique index WHERE entity active.**
   Сейчас — костыль: при архивации alias переименовывается с суффиксом `--archived-{id}`.

3. **Пройтись по `app/Enums/*`, удалить мёртвые константы.**
   Начать с `FactionStatus` — `Covert` и `Defunct` не используются.
   После — почистить check-constraint в миграциях и поле `status` в `factions`.

5. **Вернуть `WorldLoreProvider`** — сцены, лор, отношения в контекст Copilot.

6. **Силы дисциплин** (`canon_discipline_powers`) — если нужны. Заполнить сидером, добавить UI.

7. **RAG на `canon_lore_chunks`** — когда статей лора станет много (>50) и они перестанут влезать в контекст.

8. **Feature-тесты** на основные эндпоинты: auth, characters, scenes, messages, copilot.

## Вехи

- [x] 21.09.2026 — MVP заработал end-to-end в старом проекте
- [x] 24.09.2026 — MVP вынесен в чистый репозиторий `trpg_chat_helper`
- [x] 24.09.2026 — 30 статей лора импортированы
- [x] 24.09.2026 — E2E проверен в новом репозитории