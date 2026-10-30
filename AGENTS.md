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

## 🏗 Процесс разработки (Spec-Driven Development)
Мы работаем по методологии SDD. Никакой код не пишется без утвержденной спецификации.

1. **Specify:** Человек дает задачу. Ты (ИИ) создаешь папку `docs/specs/YYYY-MM-DD--feature-name/` и заполняешь `spec.md` по шаблону из `docs/specs/_template/spec.md`. Ждешь утверждения.
2. **Plan:** После утверждения спеки, заполняешь `plan.md` (архитектура, затрагиваемые файлы, риски). Ждешь утверждения.
3. **Tasks:** Разбиваешь план на шаги в `tasks.md` (с чек-листом).
4. **Implement:** Последовательно выполняешь задачи из `tasks.md`, отмечая их галочками.
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
* Тесты: `docker compose exec laravel.test php artisan test` и `docker compose exec laravel.test ./vendor/bin/pint` должны быть зелёными перед завершением работы.
* Каждая новая фича должна сопровождаться Feature-тестом (минимум один на успешный сценарий и один на ошибку), проверяющим Acceptance Criteria.

## 🛠 Полезные команды
* Тесты: `docker compose exec laravel.test php artisan test`
* Форматирование: `docker compose exec laravel.test ./vendor/bin/pint`
* Статический анализ: `docker compose exec laravel.test ./vendor/bin/phpstan analyse`
* Сборка фронта: `cd frontend && npm run build`