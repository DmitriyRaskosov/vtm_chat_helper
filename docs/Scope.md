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
Экран «Мир» из UI убран; API сущностей и отношений остаётся, потому что лист создаёт гавань и Copilot читает отношения.

### Canon (справочники, read-only, сиды)
`canon_sects`, `canon_clans`, `canon_clan_sects`, `canon_clan_relations`,
`canon_disciplines`, `canon_clan_disciplines`, `canon_discipline_powers` (пусто),
`canon_lore_entries`, `canon_lore_entry_entities`.

30 статей лора в `resources/canon/lore/*.md`, импорт через `canon:import-lore`.

### Tests

- [x] Feature-тесты: Auth, Character, Scene, Message, Copilot (25 тестов)

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

1. **RAG на `canon_lore_chunks`** — когда статей лора станет много (>50) и они перестанут влезать в контекст.

2. **Опционально: Силы дисциплин** (`canon_discipline_powers`) — если нужны. Заполнить сидером, добавить UI.

## Вехи

- [x] 21.09.2026 — MVP заработал end-to-end в старом проекте
- [x] 24.09.2026 — MVP вынесен в чистый репозиторий `trpg_chat_helper`
- [x] 24.09.2026 — 30 статей лора импортированы
- [x] 24.09.2026 — E2E проверен в новом репозитории
- [x] 25.09.2026 — Чистка кода, WorldLoreProvider вернулся - сцены, лор, отношения в контекст Copilot.
- [x] 26.09.2026 — Чистка кода, появились тесты.
- [x] LENGTH-блок в system prompt — длина драфтов следует трейтам NPC
- [x] [behavior] префикс в PersonalityProvider — трейты как директивы
- [x] Убраны дубли про клан/subtlety — карикатура побеждена архитектурно
- [x] PRIMARY DIRECTIVE: сначала - ответ на вопрос, потом - стиль
- [x] Style: максимум одна метафора, стабильное обращение (не путаются ты/вы)
- [x] Проверено: silent-кейс, yes/no-вопрос, развёрнутый рассказ, дефолт
- [x] Добавлен блок "ситуации" для сцены в окне рассказчика. Это описание локации, идёт в промпт.
- [x] Рудиментарная вкладка "МИР" для добавления лора/локаций/etc старой версии проекта удалена. 
- [x] 05.10.2026 — bge-m3 как эмбеддер (вместо qwen3-embedding:0.6b, sim вырос с 0.10 до 0.70)
- [x] Keyword boost ослаблен до 0.05
- [x] Similarity вес поднят до 0.65


## Память — работает (05.10.2026)

- [x] character_memories с pgvector(1024) + HNSW
- [x] EmbeddingProvider + OllamaEmbeddingProvider (bge-m3)
- [x] MemoryExtractionService — инкрементально, per NPC, с finish_reason tracked и source cursor
- [x] MemoryRetrievalService — cosine 0.65 + importance 0.20 + recency 0.10 + keyword 0.05
- [x] Soft diversity penalty (0.8^N)
- [x] MemoryProvider в контексте
- [x] Prompt-first retrievalQuery (topics только как supplement)
- [x] Cross-NPC retrieval: каждый помнит свою версию событий
- [x] Метакомментарии: NPC осознаёт память как «свою»

## Известные ограничения

- [ ] Extraction — ручной (artisan command)
- [ ] Большие сцены (>30 сообщений) упираются в max_tokens (6000 сейчас)
- [ ] Reranker не подключён (bge-reranker-v2-m3 требует отдельного сервиса)
- [ ] RAG по лору не сделан (world_lore обрезает статьи)

## Отложено:

- [ ] Reranker bge-reranker-v2-m3 — требует отдельного сервиса TEI sidecar (отдельный Docker-контейнер)
      Ollama не поддерживает нативно rerank API.
      Вернуться, когда retrieval станет узким горлышком (>500 записей памяти и >10 НПС, мб >15).
      Альтернатива: embedding-based rerank через bge-m3.
- [ ] Автотриггер extraction (кнопка/сцена)
- [ ] RAG по лору (canon_lore_chunks)
- [ ] Чанкование extraction для больших сцен

## Пост-MVP приоритет

1. Дневник NPC — отдельный слой от памяти
- [ ] Память = что NPC слышал/видел (внешнее).
- [ ] Дневник = что NPC делал/думал/решал (внутреннее).
- [ ] Сейчас всё в character_memories. Разделение — после reply-фикса.
2. RAG по лору (canon_lore_chunks)
3. Чанкование extraction для больших сцен

## Дневник (06.10.2026)

- [x] character_diary_entries — миграция + модель + HNSW
- [x] DiaryWriterService (L0) — запись от первого лица, лимит 120 слов
- [x] WriteDiaryJob + автотриггер (порог 15)
- [x] DiarySummarizerService (L1) — сжатие ≥3×, лимит 150 слов
- [x] SummarizeSceneDiaryJob — при закрытии сцены, только если L0 ≥ 3
- [x] Anti-anchor правило — не повторять signature-выражения
- [x] Trait-примеры работают как паттерны, не как цитаты
- [x] Voice (Абрахам-библиотекарь) сохранён в обеих уровнях

## Проверено

- L0: 1–3 абзаца, ~100 слов, голос NPC, конкретные события.
- L1: 4 предложения, ~95 слов, паттерн без verbatim, свежие формулировки.

## Осталось сделать (шаг 4+)

- [ ] DiaryRetrievalService — последняя + top-2 по семантике
- [ ] DiaryProvider в ContextAssembler
- [ ] Обновить SystemPromptProvider: DIARY блок, убрать MEMORY блоки
- [ ] Удалить character_memories + Memory* сервисы/джобы/провайдеры
- [ ] Переименовать config/memory.php → config/diary.php (старую удалить)
- [ ] Замена MemoryProvider на DiaryProvider в ContextAssembler
- [ ] Тест E2E: NPC ссылается на прошлые ночи через дневник