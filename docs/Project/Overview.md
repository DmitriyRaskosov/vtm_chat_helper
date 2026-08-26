# Обзор проекта

**VtM Chat Helper** — веб-приложение для текстовой RPG по Vampire: The Masquerade.

## Два приложения в одном репозитории

| Часть | Стек | URL (dev) |
|-------|------|-----------|
| Backend | Laravel 13 JSON API в Docker (Sail) | http://localhost:8080/api |
| Frontend | Vue 3 SPA (Vite на хосте) | http://localhost:5173 |

## Ключевые возможности (MVP)

- Регистрация и вход по логину (Sanctum Bearer tokens)
- Общий чат `messages` для всех пользователей
- Роли: [[Project/Roles]]
- RAG: индексация сообщений в `rag_chunks` (pgvector)
- Copilot: рассказчик генерирует черновики реплик НПС через Ollama и отправляет в чат

## Ограничения архитектуры

- Нет Blade, Inertia, `routes/web.php`
- Авторизация только через `Authorization: Bearer`, не сессии и не CSRF-cookie
- Vite проксирует `/api` на Laravel в dev

См. [[Architecture/Stack]].

## Дальше

См. [[Project/Roadmap]] — этап 4 (модель мира) отложен.
