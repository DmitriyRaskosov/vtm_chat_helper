# Обзор проекта

**VtM Chat Helper** — веб-приложение для текстовой RPG по Vampire: The Masquerade.

## Два приложения в одном репозитории

| Часть | Стек | URL (dev) |
|-------|------|-----------|
| Backend | Laravel 13 JSON API в Docker (Sail) | http://localhost:8080/api |
| Frontend | Vue 3 SPA (Vite на хосте) | http://localhost:5173 |

## Ключевые возможности (MVP)

- Регистрация и вход по логину (Sanctum Bearer tokens)
- Игровые сессии и сцены; отдельная лента `messages` для каждой сцены
- Роли: [[Project/Roles]]
- Раздельные scoped-корпуса pgvector и два bounded GraphRAG-контура
- Copilot: рассказчик генерирует черновики реплик НПС через Ollama и отправляет в чат
- Фундамент управления контекстом: оценка токенов и бюджетированный хвост сцены

## Ограничения архитектуры

- Нет Blade, Inertia, `routes/web.php`
- Авторизация только через `Authorization: Bearer`, не сессии и не CSRF-cookie
- Vite проксирует `/api` на Laravel в dev

См. [[Architecture/Stack]].

## Статус

Основной план из 35 этапов завершён 2026-09-11. См. [[Project/Roadmap]] и [[Project/Architecture Migration]].
