# AGENTS.md — Карта проекта для ИИ

## 🧭 Суть проекта
Чат для VtM V20 с Copilot-помощником. Backend (Laravel) собирает контекст, обращается к DeepSeek API за текстом и к локальной Ollama (в Docker) за эмбеддингами. Frontend (Vue 3 SPA).

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

## 🚫 Правила разработки
1. **Не менять `ContextAssembler`** без острой необходимости (он защищён тестами).
2. **Промпты** живут только в `resources/prompts/*.md` (через `PromptRepository`).
3. **Все LLM-параметры** (температура, лимиты) читаются только из `config/`.
4. **Фронтенд:** никаких `axios` напрямую — только `api` из `auth.js`. Логика в composables.
5. **Тесты:** `sail artisan test` и `sail pint --test` должны быть зелёными перед коммитом.

## 🛠 Команды
* Тесты: `docker compose exec laravel.test php artisan test`
* Форматирование: `docker compose exec laravel.test ./vendor/bin/pint`
* Статический анализ: `docker compose exec laravel.test ./vendor/bin/phpstan analyse`
* Сборка фронта: `cd frontend && npm run build`
