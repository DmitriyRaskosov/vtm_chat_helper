# Roadmap

## Этапы

| # | Этап | Статус |
|---|------|--------|
| 1 | Sail, PostgreSQL + pgvector, Redis, Mailpit | Готово |
| 2 | Auth (login), общий чат `messages` | Готово |
| 3 | Роли storyteller / player, панель рассказчика | Готово |
| 3.5 | RAG (`rag_chunks`), Ollama (`qwen3-embedding:0.6b`, `qwen3:8b`) | Готово |
| 4 | Модель мира: файловый лор, НПС, граф отношений, события | **Отложен** |
| 5–6 | Copilot: черновики реплик НПС + отправка в чат | **Готово (MVP)** |
| 7 | Игровые сессии, сцены и scoped messages | **Готово** |
| 8 | UI выбора и управления сценами | **Готово** |
| 9 | Context foundation: token estimate и целые L0-окна | **Готово** |
| 10 | Бюджетированный Context Builder без summaries | **Готово** |
| 11 | Трассировка запросов Copilot и выбранных drafts | **Готово** |
| 12 | Immutable L0 и фоновая идемпотентная суммаризация | **Готово** |
| 13 | Summary RAG в Context Builder с дедупликацией | **Готово** |
| 14 | L1, final scene и versioned session summaries | **Готово** |

Подробности: [[Features/Copilot]], [[Features/Scenes]], [[Architecture/Context]].

## Этап 4 (отложен)

В будущем:

- **Лор** — markdown-файлы в репозитории (правила Маскарада и т.п.) как системный контекст для LLM, не таблица в БД.
- **Хроника** — НПС, отношения, события с участниками и влиянием в PostgreSQL + синхронизация с RAG.

## Copilot (этапы 5–6)

Рассказчик вводит имя НПС и промпт → `POST /api/copilot/drafts` → бюджетированный Context Builder → 3 черновика через `qwen3:8b` → сохранение request/context provenance → правка → `POST /api/messages` с одноразовой связью на выбранный draft.

Игроки видят сценовый чат; панель Copilot доступна только рассказчику.

## Дальше (после этапа 4)

- Явные scope-профили RAG: `active_scene`, `game_session`, `global`
- Scoped retrieval и LLM tools
- Карточки НПС вместо ручного ввода имени
- Файловый лор в system prompt
- Граф отношений в контексте генерации
- Очередь для RAG (`RAG_INDEX_SYNC=false`)
