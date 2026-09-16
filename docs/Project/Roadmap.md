# Roadmap

Основной архитектурный план чата, Copilot и базы хроники завершён.

Живой пошаговый план — [[Project/ClanCard]] (карточка клана, клановые дисциплины, `regards`). Экстрактор этапы 1–4 в коде — [[Project/Extractor]]; парсер профиля клана — после ClanCard.

Подробный план из 35 этапов и критерии приёмки — в архиве [[Archive/Architecture Migration]].

## Текущее состояние

- **В работе:** карточка клана, `clan_disciplines`, ребро `regards`. [[Project/ClanCard]], [[API/World]].
- **Готово (2026-09-17):** справочник — дерево сект, `controls`/`owns`/`part_of`, колонка «Скрыть», скролл к форме, плавающие вверх/вниз. [[Features/World]], [[API/World]].
- **Готово (экстрактор):** этапы 1–4 в коде (лор, био-память, сцена → events/relations); память сцены и парсер профиля клана — после. [[Project/Extractor]], [[API/Extract]].
- **Готово:** игровые сессии и сцены, Vue-чат, token budget, Context Assembler, аудит `copilot_requests`, `search_messages`, `get_message_range`.
- **Снято:** L0/L1/final summaries, intent memory, `search_summaries`, глобальный пассивный message-RAG.
- **Этап 0:** безопасная исходная точка подтверждена 2026-09-11 (`php artisan test` 36 passed, `npm run build` зелёный; L0/intent таблиц и summary-чанков нет).
- **Этап 1:** канонический план в Obsidian и синхронизация с Cursor-планом (2026-09-11).
- **Этап 2:** Redis отключён по умолчанию (2026-09-11); opt-in `docker compose --profile future up redis`.
- **Этап 3:** хроники разведены с игровыми встречами (2026-09-11); одна active `game_session` на хронику, служебная «Основная хроника» для backfill.
- **Этап 4:** идентичность мира (`world_entities`, алиасы, архивирование вместо DELETE) (2026-09-11).
- **Этап 5:** типизированные `locations`, `factions`, `items`, `concepts` с shared PK (2026-09-11).
- **Этап 6:** ядро персонажа и переходный dual contract `npc_name`/`character_id` (2026-09-11). Финальный cutover выполнен этапом 35. Без `character_users`: 1 пользователь = 1 персонаж (`characters.user_id` unique).
- **Этап 7:** реляционный лист характеристик и специализаций (2026-09-11).
- **Этап 8:** каталоги дисциплин/сил и изученное персонажем (2026-09-11).
- **Этап 9:** текущее состояние, эффекты и журнал с optimistic revision (2026-09-11).
- **Этап 10:** цели персонажа — **снят**, таблица `character_goals` не создаётся.
- **Этап 11:** каноническая биография и immutable-версии (2026-09-11).
- **Этап 12:** отдельный векторный индекс биографии (2026-09-11).
- **Этап 13:** каталог типов мировых связей (2026-09-11).
- **Этап 14:** универсальный мировой граф `world_relations` (2026-09-11).
- **Этап 15:** межперсонажные отношения без метрик и журнала (2026-09-11).
- **Этап 16:** affiliations к фракции/месту/предмету/концепции и журнал (2026-09-11).
- **Этап 17:** внутриигровое время — **снят**.
- **Этап 18:** структурированные события мира без timeline (2026-09-11).
- **Этап 19:** канонический лор и связи с миром (2026-09-11).
- **Этап 20:** отдельный векторный индекс лора (2026-09-11).
- **Этап 21:** rulesets, rule documents, house-rule overrides (2026-09-11).
- **Этап 22:** отдельный векторный индекс правил (2026-09-11).
- **Этап 23:** knowledge grants лора и правил (2026-09-11).
- **Этап 24:** узлы личной памяти без timeline (2026-09-11).
- **Этап 25:** рёбра ассоциаций памяти (2026-09-11).
- **Этап 26:** мосты памяти к канону (2026-09-11).
- **Этап 27:** отдельные корпуса поиска; переходный dual-read завершён cutover этапа 35 (2026-09-11).
- **Этап 28:** hybrid retrieval coordinator (2026-09-11).
- **Этап 29:** GraphRAG личной памяти (2026-09-11).
- **Этап 30:** GraphRAG мира с knowledge filter (2026-09-11).
- **Этап 31:** Context Assembler — композиция providers в бюджете 12000, GraphRAG до LLM, tool loop без изменений (2026-09-11).
- **Этап 32:** канонический контекст сцены — `scene_contexts`, `scene_participants`, freeze при закрытии (2026-09-11).
- **Этап 33:** ST JSON для context/participants, `messages.user_id` anonymize, archive lore/rules (2026-09-11).
- **Этап 34:** индексы retrieval, CTE statement timeout, unique index job, логи секций/GraphRAG (2026-09-11).
- **Этап 35:** `character_id` cutover, только отдельные scoped-корпуса, удаление `rag_chunks`/legacy lore/source config, E2E provenance двух GraphRAG и финальная документация (2026-09-11). Основной план завершён.
- **После плана:** гули — `character_type=ghoul` с `domitor_character_id`; 1 пользователь = 1 PC (2026-09-12).
- **После плана:** HTTP + Vue лист V20 (stats, 7 клеток здоровья, merits/flaws, XP, compact гуль); Copilot для гулей нет; биография на листе и archive/restore персонажа (2026-09-13).
- **После плана:** ST-справочник мира (`/world`), политика фракций, «Место в мире» на листе (секта/клан/гавань) (2026-09-13). [[API/World]], [[Features/World]], [[API/Characters]].
- **После плана:** статьи лора, гриф и допуск NPC (исключения вместо нормы «кто знает») (2026-09-13). Редактор памяти — backlog. [[API/Lore]], [[Architecture/Lore]].
- **Готово (2026-09-13):** UI справочника (редактирование entities), уровни лора 0–5, набор допуска на листе, ситуационные статьи, фильтр «о ком». [[API/World]], [[API/Lore]], [[Features/World]].
- **После плана:** Copilot в два вызова — топики (вход 8000) → поиск по допуску → реплика (вход 12000) (2026-09-13). [[API/Copilot]], [[Architecture/Context]].

## 1. Игровые сессии и сцены: backend — готово

- Добавить `game_sessions`, `scenes`, статусы и связи моделей; затем nullable `messages.scene_id`, backfill всех существующих сообщений в служебную сессию/сцену и отдельным шагом сделать FK обязательным.
- Добавить storyteller-only lifecycle API: создать/активировать/закрыть сцену; чтение активной сессии и сцен. `GET/POST /messages` ограничить выбранной активной сценой, сохранив `after_id`.
- Основные точки: `routes/api.php`, `ChatController`, `Message`, миграции, `tests/Feature/ChatTest.php`.
- Приёмка: две сцены не смешивают сообщения; закрытая сцена read-only; существующие данные не теряются; полный backend suite проходит.

См. [[API/Scenes]], [[API/Messages]].

## 2. Сцены: frontend — готово

- Добавить загрузку активной сессии/сцены, переключение доступной ленты и storyteller UI для создания/закрытия сцен в `frontend/src/views/ChatView.vue`.
- Приёмка: polling и отправка всегда используют текущую сцену; игрок не видит управляющие действия; смена сцены не смешивает ленты.

См. [[Architecture/Frontend]], [[Features/Scenes]].

## 3. Основа Context: токены и бюджет — готово

- Создать слой `app/Context/`: версионируемый `TokenEstimator` и конфигурацию лимитов. Добавить `messages.token_estimate` и версию алгоритма с backfill.
- На этом этапе поведение Copilot не менялось.
- Embed-модель переведена с `nomic-embed-text` (768-d) на `qwen3-embedding:0.6b` (1024-d); финальные производные индексы разделены по корпусам.

См. [[Architecture/Context]].

## 4. Context Builder — готово

- Сборка recent history активной сцены в общем входном бюджете 12000 оценочных токенов.
- Иерархические summaries, пассивный message RAG и intent memory сняты: в prompt остаются system, запрос рассказчика и сырой хвост сцены.
- Зафиксировано: `qwen3:8b` поддерживает 32768 токенов, runtime `num_ctx=16384`, максимальный вход Context Assembler — 12000, `num_predict=3000`; оставшиеся 1384 токена — технический запас.
- Приёмка: массив `drafts` в `POST /api/copilot/drafts` не меняется, тесты проверяют состав и бюджет фактического запроса Ollama.

См. [[API/Copilot]].

## 5. Сырые запросы Copilot — готово

- Добавить `copilot_requests`: `scene_id`, storyteller, `npc_name`, исходный prompt, drafts, использованные source IDs/context metadata, модель и версии prompt/builder. При отправке выбранного draft передавать `copilot_request_id` и связывать его с итоговым сообщением.
- Инструкции рассказчика не становятся сообщениями мира и не индексируются как канонические события.
- Приёмка: каждый успешный вызов и выбранный результат трассируются; ответ обратно совместимо расширен полем `copilot_request_id`, массив `drafts` не изменён.

## 6–8, 10. Сжатие истории — снято

Таблицы `context_summaries`, `context_summary_sources`, `scene_context_states`, `storyteller_intent_summaries` и связанные jobs удалены. После сообщения и закрытия сцены LLM больше не вызывается. Канон по-прежнему в `messages`.

## 9. Retrieval и tools — готово

- `RetrievalOrchestrator` и tools `search_messages`, `get_message_range`. `search_summaries` снят вместе с L0.
- Lore/NPC/relationship tools в registry не входят; memory/world GraphRAG собирается Context Assembler до LLM.
- Приёмка: tools строго scoped по сессии/сцене, имеют лимиты, не возвращают всю историю.

См. [[Architecture/Retrieval]].

## 11. Scope-профили RAG — снято как отдельный пункт

Глобальный пассивный RAG из Context Assembler убран. Сырой хвост ограничен активной сценой; tools — текущей сессией. GraphRAG памяти и мира собирается assembler до LLM. Явные профили `active_scene` / `game_session` / `global` не вводятся: scope задаёт сборка prompt и фильтры tools.

## Дальнейшая архитектура

Состав таблиц, порядок миграций, два GraphRAG-контура, канонические биографии/лор/правила, память персонажей, мировой граф и Context Assembler — в [[Architecture/Database]], [[Architecture/Backend]] и архиве [[Archive/Architecture Migration]].
