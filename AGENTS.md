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

## 🚫 Правила разработки (Strict Rules)

### 1. Процесс и Документация (SDD)
* **Spec-First:** Перед началом любой новой фичи прочитай `docs/specs/{feature}/spec.md`. Если спеки нет — создай её (и `plan.md`, `tasks.md`) и утверди у человека. Никакого кода до утверждения спеки.
* **Трассируемость:** Реализуй только то, что описано в Acceptance Criteria. Не додумывай функционал.
* **Именование:** Все новые файлы документации (кроме корневых `README.md` и `AGENTS.md`) создавай в нижнем регистре (kebab-case).

### 2. Backend и Архитектура
* **Тонкие контроллеры:** Вся бизнес-логика — в Services (`app/`), валидация — в FormRequests, ответы — в Resources.
* **Не менять `ContextAssembler`** и ядро `Context/` без острой необходимости (оно защищено тестами и сложно настраивается).
* **Промпты** живут только в `resources/prompts/*.md` (через `PromptRepository`).
* **LLM-параметры** (температура, лимиты) читаются только из `config/`. Никаких хардкодов.

### 3. Frontend
* Никаких `axios` напрямую — только `api` из `auth.js`.
* Вся логика выносится из компонентов в composables (`use...`).

### 4. Тестирование
* Тесты: `docker compose exec laravel.test php artisan test` и `docker compose exec laravel.test ./vendor/bin/pint` должны быть зелёными перед завершением работы.
* Каждая новая фича должна сопровождаться Feature-тестом (минимум один на успешный сценарий и один на ошибку).

## 🛠 Полезные команды
* Тесты: `docker compose exec laravel.test php artisan test`
* Форматирование: `docker compose exec laravel.test ./vendor/bin/pint`
* Статический анализ: `docker compose exec laravel.test ./vendor/bin/phpstan analyse`
* Сборка фронта: `cd frontend && npm run build`