# TRPG Chat Helper (VtM V20)

AI-платформа для настольных ролевых игр с гибридной RAG-памятью NPC, динамическим управлением контекстом и генерацией ответов.

## 🚀 Engineering Highlights (для разработчиков)

Это не просто CRUD-приложение. Проект решает сложные задачи интеграции LLM в бизнес-логику:

* **Гибридный RAG (long-term memory):** поиск по дневникам NPC на базе pgvector с HNSW-индексами. Комбинирует временной якорь, косинусное сходство и коэффициент важности (importance).
* **Context Assembler (динамическое бюджетирование токенов):** система приоритизации контекста. Жёстко лимитирует бюджет токенов по источникам (лор, память, текущая сцена) и отбрасывает менее важные данные, чтобы не превысить лимиты LLM и контролировать стоимость.
* **Контроль галлюцинаций (context engineering):** очистка контекста от ложных якорей и строгое структурирование данных перед отправкой в LLM.
* **Двухпроходный пайплайн генерации:** (1) выделение тем из реплик игрока → (2) генерация трёх вариантов ответа с учётом лора, графа отношений и RAG.
* **Асинхронность и race conditions:** тяжёлые LLM-запросы в очередях (Laravel Queues). `lockForUpdate` и `afterCommit` для предотвращения дублирования сообщений и консистентности данных.
* **Строгая слоистая архитектура:** тонкие контроллеры, service layer, Policies, FormRequest, JsonResource.
* **QA / CI:** матрица доступов для всех API-маршрутов (гость, игрок, рассказчик, чужой игрок, игрок из другой хроники) — новый маршрут без строки в матрице ломает тесты. Larastan (level 5), Pint, PHPUnit.

Команды (в контейнере):

```bash
docker compose exec laravel.test php artisan test
docker compose exec laravel.test ./vendor/bin/pint
docker compose exec laravel.test ./vendor/bin/phpstan analyse
```

## 🛠 Стек

* **Backend:** PHP 8.4, Laravel 13, PostgreSQL 18 + pgvector, Sanctum, очереди в БД.
* **Frontend:** Vue 3, Vue Router, Vite 7 (Composition API).
* **AI / LLM:** DeepSeek API (текст), Ollama `bge-m3` (векторы памяти NPC).
* **Infrastructure:** Docker Desktop, Laravel Sail (`laravel.test`, `pgsql`, `worker`, `frontend`, `ollama`).
* **Testing:** PHPUnit, Larastan, Pint.

## 📋 Требования

* **Docker Desktop** (WSL2 на Windows) и **Docker Compose ≥ 2.20** (`docker compose version`).
* **DeepSeek API Key:** ключ с балансом в `.env` (`DEEPSEEK_API_KEY`). Без него Copilot не генерирует текст.

## 🚀 Быстрый старт (one-click)

1. Скопируйте `.env.example` в `.env`, при необходимости укажите `DEEPSEEK_API_KEY`, затем запустите стек:

   ```bash
   cp .env.example .env   # Windows: copy .env.example .env
   docker compose up -d --build
   ```

   Поднимаются backend, PostgreSQL, worker, фронтенд (Vite) и Ollama (профиль из `COMPOSE_PROFILES`). При старте `laravel.test` автоматически: ключ приложения, миграции; если в таблице `users` нет записей — `db:seed`, а при `DEMO_DATA=true` ещё и `demo:reset`.

2. Один раз скачайте модель эмбеддера (~1.2 ГБ), если используете Ollama из compose:

   ```bash
   docker compose exec ollama ollama pull bge-m3
   ```

На хосте не нужны: `composer install`, `npm install`, `php artisan migrate`, `php artisan key:generate`.

**Адреса:**

* Frontend (Vite): `http://localhost:5173` (порт — `FRONTEND_PORT` в `.env`)
* Backend API: `http://localhost:8080/api` (порт — `APP_PORT` в `.env`)

**Демо-вход** (если в `.env` включено `DEMO_DATA=true`, по умолчанию в `.env.example`):

* Логин: `admin`
* Пароль: `admin`

Чтобы поднять пустую БД без демо-хроники, задайте `DEMO_DATA=false` до `docker compose up`.

> Фоновые задачи (очереди, дневники NPC) обрабатывает контейнер `worker`. Проверка: `docker compose ps`.

### Ollama: CPU и GPU

В `.env.example` задано `COMPOSE_PROFILES=cpu` — поднимается сервис `ollama` без GPU.

Для NVIDIA GPU замените профиль и перезапустите (оба варианта используют сетевой алиас `ollama`; не включайте их одновременно):

```bash
# в .env: COMPOSE_PROFILES=gpu
docker compose --profile gpu up -d ollama-gpu
```

## 🛠 Решение частых проблем (Troubleshooting)

При развертывании с нуля на чистой системе могут возникнуть следующие ситуации:

**Порты заняты.** Задайте в `.env` свободные значения и перезапустите стек: `APP_PORT` (API), `FRONTEND_PORT` (Vite), `FORWARD_DB_PORT` (PostgreSQL с хоста).

**Ollama уже на хосте.** В `.env`: `OLLAMA_URL=http://host.docker.internal:11434`, не поднимайте сервис `ollama` в compose (уберите профиль `cpu`/`gpu` из `COMPOSE_PROFILES`, если он только для Ollama). Модель на хосте: `ollama pull bge-m3`.

**Пустая БД после `docker compose down -v`.** Вольюм PostgreSQL удаляется, таблица `users` снова пустая — при следующем `up` контейнер `laravel.test` сам выполнит сиды и (если `DEMO_DATA=true`) `demo:reset`. Файлы на хосте в `storage/` на это не влияют. После смены `docker-entrypoint.sh` пересоберите образ: `docker compose up -d --build`.

**Битый `APP_KEY` (склеенный base64).** В редких случаях при старте контейнера может возникнуть гонка (до применения `flock`), из-за чего в `.env` может записаться два ключа подряд.  
**Решение:** очистите значение `APP_KEY=` в `.env` и перезапустите контейнер `laravel.test` (ключ сгенерируется заново).

**403 Forbidden от Nginx (Debian).** В Debian-образе PHP-контейнера nginx по умолчанию включает `sites-enabled/default`, из-за чего запросы к приложению могут отдавать 403 вместо Laravel. В `docker/php/Dockerfile` добавлено удаление дефолтного сайта (`rm -f /etc/nginx/sites-enabled/default`), чтобы избежать конфликта маршрутизации.

## 🎲 Основной функционал (для пользователя)

* **Мульти-хроники:** несколько независимых игровых миров (строгая изоляция данных через composite FK), система приглашений.
* **Copilot:** черновики реплик NPC с учётом лора, текущей сцены и долговременной памяти. Первый зарегистрированный пользователь — рассказчик; игроки работают с чатом и своими персонажами.
* **Дневники:** векторная память NPC (pgvector), иерархическая суммаризация сцен.
* **Кубы и бой:** пулы d10, Сила Воли, генератор боевых событий.
* **UI:** готический интерфейс, две темы, разделение IC/OOC (In-Character / Out-of-Character).

## 📚 Документация

* [`AGENTS.md`](AGENTS.md) — карта модулей и правила для разработки.
* [`docs/Scope.md`](docs/Scope.md) — границы продукта.
* [`docs/ARCHITECTURE.md`](docs/ARCHITECTURE.md) — архитектура и ADR в `docs/adr/`.
