# AGENTS.md — Карта проекта для ИИ

## 🧭 Суть проекта
Чат для VtM V20 с Copilot-помощником. Backend (Laravel) собирает контекст, обращается к DeepSeek API за текстом и к Ollama (в Docker) за эмбеддингами. Frontend отдельно (Vue 3 SPA).
Главные документы для контекста: `docs/Scope.md` (границы) и `docs/ARCHITECTURE.md` (слои).

## 📂 Карта модулей (app/)
* `Character/` — листы персонажей, активные состояния (Blood/Health), архив.
* `Chronicle/` — хроники, приглашения, текущая хроника.
* `Context/` — сборка промптов (`ContextAssembler`, провайдеры).
* `Dice/` — математика бросков, Сила Воли.
* `Diary/` — генерация L0/L1 записей, векторный поиск (RAG).
* `Llm/` — интеграция с DeepSeek (ChatProvider, NpcCopilotService).
* `Messages/` — CRUD сообщений.
* `Rag/` — интеграция с Ollama (EmbeddingProvider).
* `Scene/` — управление сценами, библиотека локаций, генератор событий (EventGenerator).

## 🗺 Routing Map (Навигационная карта)

**🚨 ПРАВИЛО ДЛЯ АГЕНТА:** Прежде чем использовать `grep` или `find` по всему проекту, найди свою задачу в таблице ниже. Если задача касается известного модуля, сразу открывай указанные файлы. Это сэкономит токены и контекст.

| Feature | Backend (Laravel) | Frontend (Vue 3) |
| :--- | :--- | :--- |
| **Боевая секвенция (Ядро)** | `app/Combat/CombatService.php`<br>`app/Http/Controllers/CombatController.php` | — |
| **Боевая секвенция (UI и Логика)** | `app/Events/Combat*Event.php`<br>`app/Broadcasting/SafeBroadcaster.php` | `components/chat/CombatTracker.vue`<br>`composables/useCombatTracker.js`<br>`composables/combatTrackerOrder.js` |
| **Дневники NPC (L0/L1, RAG)** | `app/Diary/DiaryWriterService.php`<br>`app/Diary/DiarySummarizerService.php`<br>`app/Diary/DiaryRetrievalService.php`<br>`app/Jobs/*Diary*.php` | `components/chat/DiaryPanel.vue`<br>`components/workshop/DiaryManagement.vue` |
| **Копилот (Генерация ответов NPC)** | `app/Llm/NpcCopilotService.php`<br>`app/Llm/CopilotDraftService.php`<br>`app/Context/ContextAssembler.php`<br>`resources/prompts/copilot/` | `components/chat/StorytellerCopilotPanel.vue`<br>`composables/useCopilotPanel.js` |
| **Броски кубов и Макросы** | `app/Dice/DiceService.php`<br>`app/Scene/SceneDiceRollService.php`<br>`app/Http/Controllers/DiceRollController.php` | `components/chat/DiceRoller.vue`<br>`composables/useDiceMacros.js` |
| **Генератор событий (Проза)** | `app/Scene/EventGeneratorService.php`<br>`resources/prompts/copilot/event.system.md` | `components/chat/EventGeneratorModal.vue`<br>`composables/useEventGenerator.js` |
| **Управление сценой и Сессиями** | `app/Scene/SceneLifecycleService.php`<br>`app/Scene/SceneParticipantService.php`<br>`app/Scene/SceneContextService.php` | `views/ChatView.vue`<br>`components/chat/SceneManagementModal.vue`<br>`composables/useSceneSession.js` |
| **Чарлисты и Статы** | `app/Character/CharacterStatsService.php`<br>`app/Character/CharacterSheetReader.php`<br>`app/Http/Controllers/CharacterSheetController.php`<br>`config/sheet_schema.php` | `views/CharacterSheetView.vue`<br>`components/sheet/CharacterSheetGrid.vue`<br>`composables/useCharacterStats.js` |
| **Инвентарь персонажа** | `app/Character/CharacterItemService.php` | `components/chat/InventoryPanel.vue`<br>`composables/useCharacterInventory.js` |
| **Чат и Сообщения** | `app/Messages/MessageService.php`<br>`app/Messages/MessageEditService.php`<br>`app/Http/Controllers/ChatController.php` | `components/chat/ChatLog.vue`<br>`components/chat/ChatComposer.vue`<br>`composables/useChatMessages.js` |
| **WebSockets (Echo/Reverb)** | `routes/channels.php`<br>`config/reverb.php`<br>`app/Events/` | `echo.js`<br>`composables/useConnectionStatus.js` |
| **Хроники и Приглашения** | `app/Chronicle/ChronicleService.php`<br>`app/Chronicle/InvitationService.php` | `views/ChronicleListView.vue`<br>`components/layout/ChronicleSwitcher.vue` |
| **Локации (Библиотека)** | `app/Scene/SceneContextService.php`<br>`app/Chronicle/ChronicleLocationService.php` | `components/chat/LocationBanner.vue`<br>`components/chat/LocationManagerModal.vue` |
| **Мастерская (Промпты)** | `app/Prompt/PromptRepository.php`<br>`app/Prompt/PromptWorkshopService.php` | `views/WorkshopView.vue`<br>`components/workshop/PromptEditor.vue` |

**Правила работы с картой:**
1. Если задача касается **промптов** — сначала смотри `resources/prompts/` (они не хардкодятся в PHP).
2. Если задача касается **конфигов** (температура LLM, лимиты) — смотри `config/llm.php`, `config/copilot.php`, `config/diary.php`.
3. Если задача касается **событий/вебсокетов** — смотри `app/Events/` и `frontend/src/echo.js`.

## 🏗 Процесс разработки (Spec-Driven Development)
Никакой код не пишется без утверждённой спеки и плана. Шаги 1 и 2 ниже
гейтятся явным утверждением человека — переходи к следующему шагу только
после «ок».

Для написания кода используй скилл `.cursor/skills/ub-mode/SKILL.md`, но не
забывай про PROJECT OVERRIDE (SDD) в его начале. **При конфликте `SKILL.md`
и этого файла побеждает `AGENTS.md`.**

1. **Specify:** Человек дает задачу. Ты (ИИ) создаешь папку `docs/specs/YYYY-MM-DD--feature-name/` и заполняешь `spec.md` по шаблону из `docs/specs/_template/spec.md`. Ждешь утверждения.
2. **Plan:** После утверждения спеки заполняешь `plan.md` по шаблону `docs/specs/_template/plan.md` (архитектура, затрагиваемые файлы, верификация AC, риски, открытые вопросы). Ждёшь утверждения.
3. **Tasks:** Разбиваешь план на шаги в `tasks.md` (с чек-листом).
4. **Implement:** Последовательно выполняешь задачи из `tasks.md`, отмечая их галочками. Одна задача — один дифф.
5. **Validate:** Сверяешь результат с Acceptance Criteria из `spec.md`.

## 🚫 Правила разработки (Strict Rules)

### 1. Архитектура и Backend
* **Тонкие контроллеры:** Вся бизнес-логика — в Services (`app/`), валидация — в FormRequests, ответы — в Resources. Запрещено писать `DB::transaction` напрямую в контроллерах.
* **Доменные ошибки:** Сервисы бросают доменные исключения (наследники `DomainException`), контроллеры не используют `abort()`. Маппинг ошибок в HTTP-коды — в `bootstrap/app.php`.
* **Очереди:** Задачи (Jobs) диспатчатся только через `DiaryScheduler` (или аналогичные сервисы). Обязательно использовать `afterCommit()`.
* **Не менять `ContextAssembler`** и ядро `Context/` без острой необходимости (оно защищено тестами и сложно настраивается).

### 2. База данных
* **Миграции:** Только схема. Данные — в сидерах. Внешние ключи (FK) указываются в `Schema::create` ссылающейся таблицы.
* **Идемпотентность:** Все сидеры должны быть идемпотентными (использовать `upsert` или `firstOrCreate`).

### 3. LLM и Промпты
* **Промпты** живут только в `resources/prompts/*.md` (через `PromptRepository`). Нельзя хардкодить промпты в PHP.
* **LLM-параметры** (температура, лимиты) читаются только из `config/llm.php` и `config/copilot.php`.
* **JSON Mode:** При запросе структурированных данных (драфты, темы) всегда передавать `'json_object' => true` в опции `chatTurn`.

### 4. Frontend
* Никаких `axios` напрямую — только `api` из `auth.js`.
* Вся логика выносится из компонентов в composables (`use...`).
* Плавающие окна инструментов реализуются через `position: fixed` и composable `useDraggable`.

### 5. Тестирование
* Каждая новая фича сопровождается Feature-тестом (минимум один на успешный сценарий и один на ошибку), проверяющим Acceptance Criteria.
* Тесты (`php artisan test`) и Pint (`./vendor/bin/pint`) должны быть зелёными перед завершением задачи. Команды запуска — в разделе 🛠 «Полезные команды».

### 6. Правила заполнения SDD-файлов.
* **Структура `spec.md`** — в шаблоне `docs/specs/_template/spec.md`. При необходимости изменить структуру — обновляй шаблон, а не этот раздел.
* **Technical Context в `spec.md` не пишем** — все технические детали только в `plan.md`.
* **`Post-Implementation Notes`** — только при статусе `Completed`, в самом конце `spec.md`.
* **`tasks.md`** содержит только чек-лист и статус. Заметок и итогов там нет.

## 🛠 Полезные команды
Backend-команды выполняются в контейнере `laravel.test` через
`docker compose exec laravel.test ...`:
* **Тесты:** `php artisan test`
* **Форматирование:** `./vendor/bin/pint`
* **Статический анализ:** `./vendor/bin/phpstan analyse`

Сборка фронта (на хосте):
* **Сборка:** `cd frontend && npm run build`