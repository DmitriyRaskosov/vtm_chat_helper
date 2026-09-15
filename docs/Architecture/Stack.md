# Стек

Два приложения в одном репозитории:

- **Backend:** Laravel 13 в Docker (Sail). PHP 8.4, PostgreSQL + **pgvector**, Redis, Mailpit, **Ollama**. Только JSON API.
- **Frontend:** Vue 3 + Vite + Vue Router + Axios в каталоге `frontend/`. Vite на **хосте** (порт 5173), не в Docker.

Не использовать Blade, Inertia и маршруты `routes/web.php`. Браузер: `http://localhost:5173`, API: `http://localhost:8080/api/*` (в dev Vite проксирует `/api`).

Авторизация: Laravel Sanctum personal access tokens (`Authorization: Bearer`), не сессии и не CSRF-cookie.

Роли: первый зарегистрированный пользователь — **рассказчик** (`storyteller`), остальные — **игроки** (`player`). Общий чат `messages`. Подробнее: [[Project/Roles]].

## ИИ (Ollama)

- **По умолчанию:** Ollama на **хосте** (GPU). Laravel в Sail: `OLLAMA_URL=http://host.docker.internal:11434`. Pull: `ollama pull …` на хосте.
- **Опционально:** сервис `ollama` в compose (profile `docker-ollama`, CPU), `OLLAMA_URL=http://ollama:11434`.
- **qwen3-embedding:0.6b** — эмбеддинги (1024-d). **qwen3:8b** — copilot и экстрактор.
- Env: [[Development/Environment]], setup: [[Development/Setup]].

## RAG

Сообщения чата индексируются в scoped `message_embeddings` (vector + FTS). `GET /api/rag/search` требует `chronicle_id` и доступен только рассказчику. Остальные корпуса и два GraphRAG-контура собираются Context Assembler. См. [[API/RAG]], [[Architecture/Backend]].

## Copilot (рассказчик)

`POST /api/copilot/drafts` → 3 черновика реплики НПС по обязательному `character_id`. Отправка в чат: `POST /api/messages` с `character_id` + `body`; `npc_name` сохраняется backend как snapshot. UI — боковая панель в `ChatView.vue`. См. [[Features/Copilot]].

Основной архитектурный план из 35 этапов завершён. См. [[Project/Roadmap]].
