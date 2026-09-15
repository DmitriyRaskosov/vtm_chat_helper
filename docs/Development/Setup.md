# Локальная разработка

## Стек в Docker (Sail)

API в Docker: PHP 8.4, PostgreSQL с pgvector, Mailpit. **Ollama по умолчанию на хосте** (GPU); Laravel ходит на `http://host.docker.internal:11434`. Redis в обычный запуск не входит: cache, session и queue идут через PostgreSQL. Контейнер Redis — opt-in (`docker compose --profile future up redis`) и не хранит канон.

### Первый запуск

```bash
cp .env.example .env

docker run --rm -v "${PWD}:/app" -w /app composer:2 composer install --ignore-platform-reqs

docker compose up -d --build

docker compose exec laravel.test php artisan key:generate
docker compose exec laravel.test php artisan migrate
```

### Ollama на хосте (рекомендуется, GPU)

1. Установите [Ollama](https://ollama.com) на Windows/macOS/Linux.
2. Pull моделей **на хосте**:

```bat
ollama pull qwen3-embedding:0.6b
ollama pull qwen3:8b
```

3. В `.env`: `OLLAMA_URL=http://host.docker.internal:11434` (уже в `.env.example`).
4. Держите приложение Ollama запущенным. Контейнер `ollama` в compose **не нужен**.

Проверка из Sail:

```bat
docker compose exec laravel.test php artisan config:clear
docker compose exec laravel.test php artisan llm:ping
docker compose exec laravel.test php artisan rag:embed-ping
```

Если ping из контейнера не проходит (Windows): задайте системную переменную `OLLAMA_HOST=0.0.0.0:11434`, перезапустите Ollama; проверьте `curl http://127.0.0.1:11434/api/tags` на хосте и `docker compose exec laravel.test curl -s http://host.docker.internal:11434/api/tags`.

`OLLAMA_CONTEXT_LENGTH=16384` в `.env` — Laravel передаёт `num_ctx=16384` в chat. На хосте окно задаётся при запуске модели; при нехватке VRAM уменьшите значение.

После pull зарегистрируйте пользователей. RAG: `RAG_EMBEDDING_DRIVER=ollama`, `RAG_EMBEDDING_MODEL=qwen3-embedding:0.6b`, `RAG_EMBEDDING_DIMENSIONS=1024` (см. [[Development/Environment]]).

При смене embed-модели или размерности вектора подготовьте миграцию всех затронутых отдельных корпусов, затем:

```bat
docker compose exec laravel.test php artisan migrate
docker compose exec laravel.test php artisan rag:reindex-messages
```

Для обновления существующей установки до cutover этапа 35 сначала выполните `rag:reindex-messages`, затем миграции: финальная миграция удаляет legacy `rag_chunks`. Лор, правила и биографии перестраиваются специализированными indexer-сервисами.

При `RAG_INDEX_SYNC=false` сервис `queue` индексирует сообщения. Авторазбор сцен (`RunSceneExtractionJob`) идёт через тот же worker **даже если** `RAG_INDEX_SYNC=true`. `docker compose up -d` поднимает сервис `queue`; проверка: `docker compose ps queue`. Timeout воркера — 300 с (как HTTP экстрактора).

```bat
docker compose up -d queue
docker compose logs -f queue
```

Для ручного запуска: `docker compose exec laravel.test php artisan queue:work --timeout=300`. В тестах `QUEUE_CONNECTION=sync`.

### Ollama в Docker (опционально, CPU)

Без GPU, для CI или если не хотите ставить Ollama на хост:

```bat
docker compose --profile docker-ollama up -d ollama
docker compose exec ollama ollama pull qwen3-embedding:0.6b
docker compose exec ollama ollama pull qwen3:8b
```

В `.env`: `OLLAMA_URL=http://ollama:11434`.

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
| Ollama (хост) | http://127.0.0.1:11434 (из Laravel: `host.docker.internal:11434`) |

Корень `http://localhost:8080/` без `/api` отвечает **404**: у Laravel нет веб-страниц, UI только на Vite.

## Obsidian

Открыть vault: **Open folder as vault** → каталог `docs/` в корне репозитория. См. [[Meta/Documentation]].
