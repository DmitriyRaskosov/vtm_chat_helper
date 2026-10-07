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