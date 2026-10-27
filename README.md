# TRPG Chat Helper (VtM V20)

Чат для настольных ролевых игр (Vampire: The Masquerade V20) с Copilot-помощником рассказчика, ведением дневников NPC, бросками кубиков и генерацией боевых событий.

## 📋 Требования

* **Docker Desktop** (с поддержкой WSL2 на Windows).
* **DeepSeek API Key:** ключ с балансом (без него Copilot не генерирует текст). Укажите в `.env` после первого запуска или заранее скопируйте `.env.example` → `.env`.

## 🚀 Быстрый старт (one-click)

1. Скопируйте `.env.example` в `.env` (нужно Compose для профиля Ollama и опционально `DEEPSEEK_API_KEY`), затем запустите стек:

   ```bash
   cp .env.example .env   # Windows: copy .env.example .env
   docker compose up -d --build
   ```

   Миграции, сиды и при `DEMO_DATA=true` демо-данные применяются автоматически при старте `laravel.test`.

2. Один раз скачайте модель эмбеддера (~1.2 ГБ):

   ```bash
   docker compose exec ollama ollama pull bge-m3
   ```

Ручные `composer install`, `npm install` на хосте и `php artisan migrate` не нужны.

**Адреса:**

* Frontend (Vite): `http://localhost:5173`
* Backend API: `http://localhost:8080/api` (порт задаётся `APP_PORT` в `.env`)

**Демо-вход** (если в `.env` включено `DEMO_DATA=true`, по умолчанию в `.env.example`):

* Логин: `admin`
* Пароль: `admin`

Чтобы поднять пустую БД без демо-хроники, задайте `DEMO_DATA=false` до `docker compose up`.

> Фоновые задачи (очереди, дневники NPC) обрабатывает контейнер `worker`. Проверка: `docker compose ps`.

### Ollama: CPU и GPU

В `.env.example` задано `COMPOSE_PROFILES=cpu` — поднимается сервис `ollama` без GPU.

Для NVIDIA GPU замените профиль и перезапустите (оба варианта используют сетевой алиас `ollama`, не включайте их одновременно):

```bash
# в .env: COMPOSE_PROFILES=gpu
docker compose --profile gpu up -d ollama-gpu
```

## 🛠 Стек

* **Backend:** PHP 8.4, Laravel 13, PostgreSQL 18 + pgvector, очереди в БД.
* **Frontend:** Vue 3, Vue Router, Vite 7 (Composition API), dev-сервер в Docker.
* **AI / LLM:** DeepSeek API (текст), Ollama `bge-m3` (векторы памяти NPC).
* **Тестирование:** PHPUnit, Larastan, Pint.

## 🎲 Основной функционал

* **Мульти-хроники:** несколько независимых игровых миров, приглашения.
* **Copilot:** черновики реплик NPC с учётом лора, сцены и памяти.
* **Дневники:** векторная память NPC (pgvector), суммаризация сцен.
* **Кубы и бой:** пулы d10, Сила Воли, генератор боевых событий.
* **UI:** готический интерфейс, две темы, IC/OOC.
