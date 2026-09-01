# Локальная разработка

## Стек в Docker (Sail)

API в Docker: PHP 8.4, PostgreSQL с pgvector, Redis, Mailpit, **Ollama** (порт 11434 **не** на хост — только `http://ollama:11434` из Laravel).

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

При смене embed-модели или размерности вектора:

```bat
docker compose exec laravel.test php artisan migrate
docker compose exec laravel.test php artisan rag:reindex-messages
```

Миграция `resize_rag_chunks_embedding_for_qwen3` очищает `rag_chunks` и меняет размерность колонки; lore-чанки нужно проиндексировать заново через `rag:index-lore`.

`php artisan queue:work` — только если `RAG_INDEX_SYNC=false`.

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
| Mailpit | http://localhost:8025 |
| Ollama | только внутри Docker (`http://ollama:11434`) |

## Obsidian

Открыть vault: **Open folder as vault** → каталог `docs/` в корне репозитория. См. [[Meta/Documentation]].
