# Локальная разработка

## Стек в Docker (Sail)

API в Docker: PHP 8.4, PostgreSQL с pgvector, Mailpit, **Ollama** (порт 11434 **не** на хост — только `http://ollama:11434` из Laravel). Redis в обычный запуск не входит: cache, session и queue идут через PostgreSQL. Контейнер Redis — opt-in (`docker compose --profile future up redis`) и не хранит канон.

### Первый запуск

```bash
cp .env.example .env

docker run --rm -v "${PWD}:/app" -w /app composer:2 composer install --ignore-platform-reqs

docker compose up -d --build

docker compose exec laravel.test php artisan key:generate
docker compose exec laravel.test php artisan migrate
```

### Ollama — pull моделей в контейнер `ollama`

```bat
docker compose exec ollama ollama pull qwen3-embedding:0.6b
docker compose exec ollama ollama pull qwen3:8b
```

После pull зарегистрируйте пользователей заново. В `.env`: `RAG_EMBEDDING_DRIVER=ollama`, `RAG_EMBEDDING_MODEL=qwen3-embedding:0.6b`, `RAG_EMBEDDING_DIMENSIONS=1024` (см. [[Development/Environment]]).

Ollama запускается с `OLLAMA_CONTEXT_LENGTH=16384`; Laravel также передаёт `num_ctx=16384` и `num_predict=3000` для каждого chat-запроса. После изменения compose пересоздайте сервис и проверьте фактическое окно:

```bat
docker compose up -d --force-recreate ollama
docker compose exec ollama ollama run qwen3:8b "ping"
docker compose exec ollama ollama ps
```

В колонке `CONTEXT` для `qwen3:8b` ожидается `16384`. Большое окно требует больше памяти.

При смене embed-модели или размерности вектора подготовьте миграцию всех затронутых отдельных корпусов, затем:

```bat
docker compose exec laravel.test php artisan migrate
docker compose exec laravel.test php artisan rag:reindex-messages
```

Для обновления существующей установки до cutover этапа 35 сначала выполните `rag:reindex-messages`, затем миграции: финальная миграция удаляет legacy `rag_chunks`. Лор, правила и биографии перестраиваются специализированными indexer-сервисами.

При `RAG_INDEX_SYNC=false` сервис `queue` индексирует сообщения. При значении по умолчанию `true` очередь для Copilot не нужна.

```bat
docker compose logs -f queue
```

Для ручного запуска: `docker compose exec laravel.test php artisan queue:work`. В тестах `QUEUE_CONNECTION=sync`.

### Windows

Вместо `./vendor/bin/sail` — `sail.cmd`:

```bat
sail.cmd artisan migrate
sail.cmd down
```

PHP, Composer и Artisan — **внутри контейнера**, не на хосте.

## Frontend (на хосте)

Порт 5173 не в Docker — Vite локально:

```bash
cd frontend
npm install
npm run dev
```

## URL после запуска

| Сервис | URL |
|--------|-----|
| Vue UI | http://localhost:5173 |
| Laravel API | http://localhost:8080/api |
| Laravel health | http://localhost:8080/up |
| Mailpit | http://localhost:8025 |
| Ollama | только внутри Docker (`http://ollama:11434`) |

Корень `http://localhost:8080/` без `/api` отвечает **404**: у Laravel нет веб-страниц, UI только на Vite.

## Obsidian

Открыть vault: **Open folder as vault** → каталог `docs/` в корне репозитория. См. [[Meta/Documentation]].
