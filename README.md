# TRPG Chat Helper

Чат для настольных RPG с Copilot-помощником рассказчика.
Сеттинг: Vampire: The Masquerade V20.

## Что умеет

- Чат сцен: мастер и игроки пишут от своих персонажей.
- Copilot: мастер пишет синопсис → DeepSeek разворачивает в 3 варианта реплики NPC.
- Канон: секты, кланы, дисциплины V20 — встроенный справочник.
- Лист персонажа: клан, секта, дисциплины, биография.

Первый зарегистрированный пользователь получает роль рассказчика (storyteller).

## Стек

- Backend: Laravel 13, PostgreSQL 18, Sanctum
- Frontend: Vue 3, Vite (отдельное приложение на 5173)
- LLM: DeepSeek API
- Контейнеры: Docker Desktop (Laravel Sail)
- Node.js 20+
- Аккаунт DeepSeek (для API-ключа) — https://platform.deepseek.com

## Запуск локально

### Backend

```bash
cp .env.example .env
# заполнить DEEPSEEK_API_KEY

docker compose up -d
# или: ./vendor/bin/sail up -d

docker compose exec laravel.test php artisan key:generate
docker compose exec laravel.test php artisan migrate:fresh --seed
```

`canon:import-lore` уже вызывается из `DatabaseSeeder`. Повторный импорт лора:

```bash
docker compose exec laravel.test php artisan canon:import-lore
```

API: http://localhost:8080/api

### Frontend

```bash
cd frontend
npm install
npm run dev
```

UI: http://localhost:5173 (прокси `/api` → backend на 8080)

## Структура

- `app/` — backend (Laravel)
- `frontend/` — SPA (Vue)
- `resources/canon/lore/` — .md файлы канона
- `database/seeders/Canon*.php` — сиды канона

## Переменные окружения

См. `.env.example`. Ключевые:

- `DEEPSEEK_API_KEY` — ключ DeepSeek API (обязательно для Copilot. https://platform.deepseek.com)
- `LLM_DRIVER=deepseek`
- `DB_*` — PostgreSQL (Sail: host `pgsql`, user `sail`)