# Scope

Живой документ. Обновлять при изменении границ.

## Что такое проект

Чат для VtM V20 с Copilot-помощником рассказчика.
Мастер пишет синопсис → Copilot разворачивает в 3 варианта реплики NPC → мастер отправляет в чат.
NPC ведут долговременный дневник, который подмешивается в контекст.

**Не в проекте:** механика (кубы, статы, здоровье), рулбук, world events, streaming.

## Что работает сейчас (07.10.2026)

### Auth / Game / Chat
- Регистрация, логин, логаут, /user.
- Хроники, сессии, сцены, участники, контекст сцены.
- Сообщения от мастера, игрока, NPC.

### Copilot
- `POST /api/copilot/drafts` — синопсис → 3 драфта.
- DeepSeek (`deepseek-chat`).
- Промпты вынесены в `resources/prompts/` + `PromptRepository`.
- Контекст: NPC-identity, сцена, лор, storyteller-промпт, recent_messages, direct_relations, personality, biography, diary, closing.
- Anti-anchor, quote discipline, speech act, trait examples, length rules.

### Дневник
- `character_diary_entries` с pgvector HNSW.
- L0 (каждые 15 сообщений или при закрытии), L1 (сводка сцены при close).
- bge-m3 (1024 dim, sim ~0.70 на релевантных парах).
- Retrieval: последняя + top-2 семантики, L1 приоритетнее L0.
- UI: список, открыть, редактировать, удалить, пересоздать.

### Characters
- Создание, чтение, идентичность, дисциплины, биография, traits, место (клан/секта/гавань), архив.

### World
- `world_entities`, `world_relations`, `world_relation_types`, альясы. CRUD + neighbors.
- Экран «Мир» из UI убран, API сущностей и отношений остаётся (гавань, отношения для Copilot).

### Canon
- `canon_sects`, `canon_clans`, `canon_clan_sects`, `canon_clan_relations`,
  `canon_disciplines`, `canon_clan_disciplines`, `canon_discipline_powers` (пусто),
  `canon_lore_entries`, `canon_lore_entry_entities`.
- 30 статей лора в `resources/canon/lore/*.md`, импорт `canon:import-lore`.

### Tests
- 25 feature-тестов: Auth, Character, Scene, Message, Copilot.

## Заморожено (не удалено, не развивается)

- Механика: stats, health, merits, experience, status. По сути - все броски кубов из чарлиста.
- `CanonDisciplinePower`, `CharacterPower`.
- RAG: `LoreChunk`, `MessageEmbedding`, `RagSearchController`.
- Extraction: `Extractor/*`, `ExtractionRun`, `ExtractController`.
- Rulebook: `RuleDocument*`, `Ruleset`.
- World events: `WorldEvent*`.
- Старый lore (не canon): `LoreEntry`, `LoreChunk`.

## Правила

- **Миграции:** только схема. Данные — в сидерах. FK в `Schema::create` той таблицы, что ссылается.
- **Правки:** только активные роуты и их потребители. Замороженное не расширять.
- **Канон vs хроника:** `canon_*` — read-only, сиды. Всё остальное — данные игры.
- **Разное:** Не использовать `DB::table(...)->insert(...)` внутри миграций. Исключение: миграции данных (data migrations), когда нужно перенести существующие строки. В текущем проекте таких нет.

## TODO — приоритет 1 (до ваншота)

### Проблема A. NPC ушёл со сцены — финальный L0
- [ ] Миграция: `scene_participants.entered_message_id`, `left_message_id` (nullable bigint).
- [ ] `DiaryWriterService::writeForScene` — фильтр сообщений по окну присутствия каждого NPC.
- [ ] Триггер при `leave`: `WriteFinalDiaryForNpcJob` — финальный L0 для ушедшего.
- [ ] Feature-тест: NPC входит на 5, уходит на 42 → L0 покрывает сообщения 5–42.

### Проблема B. OOC-сообщения
- [ ] Миграция: `messages.is_ooc BOOLEAN DEFAULT false`.
- [ ] UI: toggle «IC / OOC» в `ChatComposer`, OOC — приглушённый стиль.
- [ ] `DiaryWriterService` — фильтр `where('is_ooc', false)`.
- [ ] `RecentMessagesProvider` — решить, подавать ли OOC в контекст Copilot.
- [ ] Feature-тест: OOC-сообщения не попадают в дневник.

### Проблема C. RAG по сообщениям сцены
- [ ] Миграция: `message_embeddings` (message_id, chronicle_id, scene_id, embedding, model).
- [ ] `MessageEmbedderService` + job — эмбеддить при `POST /messages` (батчем, асинхронно).
- [ ] `SceneRecallProvider` — если сцена >50 сообщений → top-3 семантически близких за пределами `recent_messages`.
- [ ] Секция `[scene recall]` — «не цитируй дословно, используй как контекст».
- [ ] `embedBatch` для экономии вызовов; обрезка очень длинных (>20k символов) до 5000+2000.

## TODO — приоритет 2 (после ваншота)

- [ ] Feature-тесты на дневник (3 теста: L0 создаётся, L1 создаётся, retrieval работает).
- [ ] Переименовать `scenes.last_extracted_to_message_id` → `last_diary_to_message_id`.
- [ ] `writeForScene` — цикл по батчам при >60 необработанных сообщений.
- [ ] Проблема D: игровое время (`chronicles.current_in_game_date`, `in_game_date` в дневнике, кнопка «+5 мин / +1 час»).
- [ ] Наполнение канона: 30 → 50+ статей.
- [ ] Ручное создание записей в дневнике через UI.
- [ ] Метрики SkyrimNET: `importance`, `emotion`, `tags`, `location` в дневник.
- [ ] Слот для пустых трейтов персонажа.
- [ ] Retry если `finish_reason=length` в L1.

## Отложено по триггеру

- [ ] RAG по лору (`canon_lore_chunks`) — когда статей >50.
- [ ] Иерархия L2 дневника — когда записей на NPC >100.
- [ ] Prune дневника — когда записей >1000.
- [ ] UI промптов (read-only) — если понадобится.
- [ ] Reranker `bge-reranker-v2-m3` — когда >500 записей. Требует TEI sidecar.
- [ ] HippoRAG / RAPTOR — только при масштабе >500 сцен.

## Может, никогда

- Memory вернуть — только если дневник чего-то не покрывает.
- Streaming, Voice/TTS, WebSockets.

## Вехи

- [x] MVP end-to-end в старом проекте.
- [x] вынесен в чистый репозиторий, 30 статей лора.
- [x] WorldLoreProvider вернулся (сцены, лор, отношения).
- [x] 25 feature-тестов.
- [x] LENGTH, anti-anchor, quote discipline, speech act.
- [x] bge-m3 (sim 0.10 → 0.70).
- [x] дневник L0 + L1 E2E, memory удалена, промпты в `resources/prompts/`.
- [x] UI дневника (просмотр, редактирование, удаление, пересоздание).

## Demo stand (08.10.2026)

- [x] `DemoSeeder` — 3 NPC (Абрахам, Ханна, Август), сцена, 15 сообщений
- [x] `php artisan demo:reset` — быстрый сброс без migrate:fresh
- [x] `WithoutModelEvents` убран из DatabaseSeeder (hooks работают)
- [x] Миграция `create_chronicles` больше не вставляет данные
- [x] Demo теперь id=1, ручных DELETE не нужно

## Soft-delete + edit + UI (08.10.2026)

- [x] messages.deleted_at (SoftDeletes), partial index на активные
- [x] character_diary_entries.is_stale + partial index
- [x] MessageEditService: toggleOoc, softDelete, restore
- [x] Помечает L0/L1 (from <= id <= to) как stale при изменении источника
- [x] Endpoints: PATCH /messages/{id}/ooc, DELETE /messages/{id}, POST /messages/{id}/restore
- [x] Права: storyteller — что угодно; игрок — только свои
- [x] DiaryRetrievalService пропускает is_stale
- [x] serialize отдаёт is_ooc, is_deleted, is_stale
- [x] Тесты soft-delete и OOC (MessageEditTest, 8 тестов)
- [x] Итого: 44 теста (Auth, Character, Scene, Message, Copilot, Diary, MessageEdit)
- [x] Кнопки у сообщений: toggle OOC, delete, restore
- [x] Toggle «Показать удалённые» (только storyteller)
- [x] Удалённые сообщения — серые, зачёркнутые, бейдж УДАЛЕНО
- [x] Stale-метка в дневнике (оранжевая рамка + бейдж)
- [x] Права: storyteller — что угодно; игрок — только свои
- [x] Тесты: 8 (MessageEditTest)

## Ваншот (08.10.2026) — прошёл

Проведён полный игровой тест. Механики работали, дневник работал.

### Найдено (12 пунктов, сгруппировано)

**Группа 1. UX-блокеры (сейчас)**
- [ ] Игроки не видят список НПС сцены
- [ ] Промпт в панели Copilot не очищается после отправки драфта
- [ ] Лимит символов на промпт рассказчика (≤50 слов) — поднять

**Группа 2. Длина ответов (следующее)**
- [ ] Убрать/переработать жёсткий LENGTH-блок
- [ ] max_tokens для reply 2500 → 4000
- [ ] storyteller_prompt 800 → 1500
- [ ] Открывает пункты: хитрость (5), локация (7), действия (8), длина (10)

**Группа 3. Контекст**
- [ ] L1 слишком сжимает — поднять лимит до 250-300 слов
- [ ] biography 500 → 1000-1200
- [ ] Убедиться, что большие биографии влезают

**Группа 4. Свой-чужой**
- [ ] SceneProvider: показать секту/клан каждого участника
- [ ] Плюс блок «Союзы и вражда» из world_relations

### Заметки

- DeepSeek имеет собственные знания лора VtM — использует, когда БД не покрывает.
- Локация пока не используется NPC — возможно, уйдёт само после группы 2.

## Известные нюансы

- `include_deleted` в query — не валидируется через rule 'boolean',
  используется `$request->boolean()`. Стандартный паттерн для флагов.

  ### Группа 1. UX-блокеры — ЗАКРЫТО (09.10.2026)

- [x] Игроки видят список участников сцены
- [x] Промпт очищается после отправки драфта
- [x] Лимит промпта: 2000 → 6000 символов
- [x] Лимит body: 4000 → 8000
- [x] storyteller_prompt.max: 800 → 1500 токенов
- [x] Большие textarea (8–10 строк), скролл драфтов

### Группа 2. Длина ответов — ЗАКРЫТО (10.10.2026)

- [x] HARD OVERRIDE работает: «коротко/резко/едко» → 1 предложение, ≤15 слов
- [x] Промпт «развёрнуто» → 6–10 предложений
- [x] ACONS подчиняется override
- [x] Убран «expand» из TASK (он перебивал LENGTH)
- [x] Титулы: `title` trait + `SceneProvider` с сектой/кланом
- [x] PC-flow: `myCharacterId` в composer, `character_id` в сообщении

### Группа 3. Контекст — ЗАКРЫТО (10.10.2026)

- [x] Лимиты подняты: biography 1200→2000, personality 1000→1800, npc_identity 400
- [x] max_input_tokens: 12000 → 14000
- [x] context_length: 16384 → 32768
- [x] Проверено: Порижана (biggest NPC) влезает целиком (8704 / 14000)

### Осталось

- [ ] Группа 4 (свой/чужой): только базово. Тонкая настройка альянсов — потом.
- [ ] L1 слишком сжимает — поднять лимит до 250-300 слов, max_tokens summarizer 500 → 1000
- [ ] system-блок (2950 токенов) — кандидат на сжатие в будущем
- [ ] Cache hit 25-30% — вырастет, когда промпт застынет
- [ ] Ручное создание записей в дневнике
- [ ] Метрики SkyrimNET (importance, emotion, tags)