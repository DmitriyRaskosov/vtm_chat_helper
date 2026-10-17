# Scope

Живой документ. Обновлять при изменении границ.

## Что такое проект

Чат для VtM V20 с Copilot-помощником рассказчика.
Мастер пишет синопсис → Copilot разворачивает в 3 варианта реплики NPC → мастер отправляет в чат.
NPC ведут долговременный дневник, который подмешивается в контекст.
Проект уже не MVP, скорее Early Access с ограниченным и постоянно дополняющимся функционалом.

**Не в проекте:** механика (кубы, статы, здоровье), рулбук, world events.

## Что работает сейчас (17.10.2026)

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
- Создание, чтение, идентичность, дисциплины, биография, traits, место (клан/секта), архив.

### World
- `world_entities` — identity для characters/locations; `world_relations` — read-only связи (ADR-002). `WorldRelationKind` — 13 унаследованных значений enum, в коде приложения не используются.

### Canon
- `canon_sects`, `canon_clans`, `canon_clan_sects`, `canon_clan_relations`,
  `canon_disciplines`, `canon_clan_disciplines`, `canon_discipline_powers` (пусто),
  `canon_lore_entries`, `canon_lore_entry_entities`.
- 30 статей лора в `resources/canon/lore/*.md`, импорт `canon:import-lore`.

### Демо-данные
- `DatabaseSeeder` — только канон + `ChronicleSeeder` (хроника «Моя первая хроника» без сцен) + `canon:import-lore`; **не** вызывает `DemoSeeder`.
- `php artisan demo:reset` — сброс данных игры (TRUNCATE) с сохранением канона и пользователей, затем `DemoSeeder`: рассказчик `admin` / `admin`, «Demo Chronicle», «Demo Session», активная сцена «Встреча на пустыре», 5 NPC-участников (Абрахам Леру, Август Сент-Клер, Гидеон Эш, Ильзе Фогель, Порижана) с биографиями и traits; **сообщений в чате не создаёт**.

### Tests
- 84 теста: 80 Feature + 4 Unit (`rg -c "function test_" tests`).
- Feature: Auth (5), Character (11), CharacterTrait (2), CharacterVisibility (4), Scene (8), Message (3), MessageEdit (8), Copilot (4), Diary (16), DiaryLlmServices (12), DemoReset (3), DirectRelationsProvider (1), Smoke (2), WorldEntityDelete (1).
- Unit: DiaryMetadataParser (4).

## Правила

- **Миграции:** только схема. Данные — в сидерах. FK в `Schema::create` той таблицы, что ссылается.
- **Правки:** только активные роуты и их потребители. Замороженное не расширять.
- **Канон vs хроника:** `canon_*` — read-only, сиды. Всё остальное — данные игры.
- **Разное:** Не использовать `DB::table(...)->insert(...)` внутри миграций. Исключение: миграции данных (data migrations), когда нужно перенести существующие строки. В текущем проекте таких нет.

## Вехи

- [x] MVP end-to-end в старом проекте.
- [x] вынесен в чистый репозиторий, 30 статей лора.
- [x] WorldLoreProvider вернулся (сцены, лор, отношения).
- [x] 25 feature-тестов.
- [x] LENGTH, anti-anchor, quote discipline, speech act.
- [x] bge-m3 (sim 0.10 → 0.70).
- [x] дневник L0 + L1 E2E, memory удалена, промпты в `resources/prompts/`.
- [x] UI дневника (просмотр, редактирование, удаление, пересоздание).
- [x] `DemoSeeder` — рассказчик `admin`, «Demo Chronicle», «Demo Session», сцена «Встреча на пустыре», 5 NPC-участников (Абрахам Леру, Август Сент-Клер, Гидеон Эш, Ильзе Фогель, Порижана); сообщений не создаёт
- [x] `php artisan demo:reset` — быстрый сброс без migrate:fresh
- [x] `WithoutModelEvents` убран из DatabaseSeeder (hooks работают)
- [x] Миграция `create_chronicles` больше не вставляет данные
- [x] Demo теперь id=1, ручных DELETE не нужно
- [x] Retrieval: L1 + L0 в одном пуле, семантика по storyteller prompt, из пула исключаются записи с from_message_id >= min(id) окна recent_messages (устранение дублей с секцией recent).

## Ваншот (08.10.2026) — прошёл

Проведён полный игровой тест. Механики работали, дневник работал.

## TODO
- [ ] Переход с хаотичного добавления фич на управляемую архитектуру.
- [ ] `writeForScene` — цикл по батчам при >60 необработанных сообщений.
- [ ] Проблема D: игровое время (`chronicles.current_in_game_date`, `in_game_date` в дневнике, кнопка «+5 мин / +1 час»).
- [ ] Наполнение канона: 30 → 50+ статей.
- [ ] Ручное создание записей в дневнике через UI.
- [ ] Метрики SkyrimNET: `importance`, `emotion`, `tags`, `location` в дневник.
- [ ] Retry если `finish_reason=length` в L1.
- [ ] Группа 4 (свой/чужой): только базово. Тонкая настройка альянсов — потом.
- [ ] UI - добавить функционал простого рисования и прикреплять фотографии. В будущем - поменять дизайн UI целиком.

## TODO, но отложено

- [ ] RAG по лору (`canon_lore_chunks`) — когда статей >50.
- [ ] Иерархия L2 дневника — когда записей на NPC >100.
- [ ] Prune дневника — когда записей >1000.
- [ ] UI промптов (read-only) — если понадобится.
- [ ] Reranker `bge-reranker-v2-m3` — когда >500 записей. Требует TEI sidecar.
- [ ] HippoRAG / RAPTOR — только при масштабе >500 сцен.

### Заметки

- DeepSeek имеет собственные знания лора VtM — использует, когда БД не покрывает.
- Локация пока не используется NPC — возможно, уйдёт само после группы 2.

### Известные нюансы

- `include_deleted` в query — не валидируется через rule 'boolean',
  используется `$request->boolean()`. Стандартный паттерн для флагов.