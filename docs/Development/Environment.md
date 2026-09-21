# Переменные окружения

Ключевые переменные для RAG, Ollama и copilot (см. `.env.example`).

## RAG и Ollama

| Переменная | По умолчанию | Описание |
|------------|--------------|----------|
| `RAG_EMBEDDING_DIMENSIONS` | `1024` | Размер вектора эмбеддинга |
| `RAG_EMBEDDING_DRIVER` | `ollama` | `ollama` в dev; в тестах `stub` (см. [[Development/Testing]]) |
| `RAG_INDEX_SYNC` | `true` | `true` — индексация RAG синхронно после сообщения; `false` — через очередь (`queue:work`) |
| `OLLAMA_URL` | `http://host.docker.internal:11434` | Ollama на хосте (GPU). Docker CPU: `http://ollama:11434` + profile `docker-ollama` |
| `RAG_EMBEDDING_MODEL` | `qwen3-embedding:0.6b` | Модель эмбеддингов |
| `OLLAMA_CHAT_MODEL` | `qwen3:8b` | Модель генерации черновиков copilot |
| `OLLAMA_CONTEXT_LENGTH` | `16384` | Размер runtime context window Ollama (`num_ctx`) |
| `OLLAMA_MAX_OUTPUT_TOKENS` | `3000` | Максимум токенов генерации (`num_predict`) |

## LLM (Copilot chat)

| Переменная | По умолчанию | Описание |
|------------|--------------|----------|
| `LLM_DRIVER` | `ollama` | `ollama` — локальная Ollama; `deepseek` — внешний API |
| `LLM_JSON_MODE` | `true` | JSON-ответы, если провайдер поддерживает |
| `LLM_MAX_OUTPUT_TOKENS` | `3000` | Лимит генерации (перекрывается per-request) |
| `LLM_TEMPERATURE` | `0.7` | Температура по умолчанию |
| `DEEPSEEK_API_KEY` | — | **Только локальный `.env`**, не коммитить. Нужен при `LLM_DRIVER=deepseek` |
| `DEEPSEEK_BASE_URL` | `https://api.deepseek.com/v1` | Base URL DeepSeek API |
| `DEEPSEEK_CHAT_MODEL` | `deepseek-chat` | Модель чата |
| `DEEPSEEK_TIMEOUT` | `120` | HTTP-таймаут (сек) |

Конфиг: `config/llm.php`. Реализация: `app/Llm/DeepSeekChatProvider.php`, биндинг в `AppServiceProvider`. Embeddings и RAG по-прежнему через Ollama (`RAG_*`, `OLLAMA_*`).

## Copilot (опционально)

| Переменная | По умолчанию | Описание |
|------------|--------------|----------|
| `COPILOT_HISTORY_LIMIT` | `30` | Сколько последних сообщений в контекст **реплики** |
| `COPILOT_TOPIC_HISTORY_LIMIT` | `8` | Короткий хвост сцены для вызова топиков |
| `COPILOT_TOPIC_MAX_OUTPUT_TOKENS` | `384` | `num_predict` вызова топиков |
| `COPILOT_TOPIC_TEMPERATURE` | `0.2` | Температура вызова топиков |
| `COPILOT_DRAFT_COUNT` | `3` | Количество черновиков |
| `COPILOT_TOOLS_ENABLED` | `true` | Tool-call loop Copilot |
| `COPILOT_TOOLS_MAX_ITERATIONS` | `2` | Максимум раундов tools |
| `COPILOT_TOOLS_SEARCH_LIMIT` | `5` | Лимит `search_messages` |
| `COPILOT_TOOLS_RANGE_LIMIT` | `20` | Лимит `get_message_range` |

Конфиг: `config/rag.php`, `config/ollama.php`, `config/copilot.php`.

## Extractor (этап 1)

| Переменная | По умолчанию | Описание |
|------------|--------------|----------|
| `EXTRACTOR_DRIVER` | `ollama` | `ollama` — локальная Ollama; `none` — POST `/api/extract` → 503; `openai_compat` — задел, не реализован |
| `EXTRACTOR_OLLAMA_MODEL` | `OLLAMA_CHAT_MODEL` / `qwen3:8b` | Модель экстрактора; Copilot не меняется |
| `EXTRACTOR_TEMPERATURE` | `0.1` | Низкая температура JSON-ответа |
| `EXTRACTOR_HTTP_TIMEOUT_SECONDS` | `300` | HTTP-таймаут вызова Ollama из экстрактора (Copilot остаётся на 180 с) |
| `EXTRACTOR_THINK` | `false` | `think: false` в теле `/api/chat` — reasoning выкл., JSON в `content` |
| `EXTRACTOR_CHARACTERS_PER_TOKEN` | `2` | Оценка токенов экстрактора: `ceil(mb_strlen / 2)`; Copilot по-прежнему `/3` |
| `EXTRACTOR_LORE_ARTICLE_MAX_CHARS` | `10000` | Макс. символов статьи в одном POST лора |
| `EXTRACTOR_LORE_SYSTEM_TOKENS` | `3000` | Жёсткий бюджет system prompt лора (тест) |
| `EXTRACTOR_LORE_CATALOG_TOKENS` | `1000` | Обрезка каталога в промпте лора |
| `EXTRACTOR_LORE_OUTPUT_TOKENS` | `7128` | `num_predict` лора (`16384 − вход − 256`) |
| `EXTRACTOR_SCENE_SYSTEM_TOKENS` | `1800` | Бюджет system prompt сцены |
| `EXTRACTOR_SCENE_CATALOG_TOKENS` | `1000` | Каталог + участники сцены |
| `EXTRACTOR_SCENE_FEED_TOKENS` | `5392` | Корзина ленты сообщений |
| `EXTRACTOR_SCENE_INPUT_TOKENS` | `8192` | Кап всего входа сцены |
| `EXTRACTOR_SCENE_OUTPUT_TOKENS` | `7936` | `num_predict` сцены |
| `EXTRACTOR_SCENE_MESSAGE_LIMIT` | `30` | Макс. сообщений в одном окне сцены (раньше при токен-стопе) |
| `EXTRACTOR_BIOGRAPHY_OUTPUT_TOKENS` | `7128` | `num_predict` биографии |
| `EXTRACTOR_CATALOG_MAX_ENTITIES` | `150` | Макс. строк справочника до обрезки по токенам профиля |

Конфиг: `config/extractor.php`. См. [[API/Extract]], [[Project/Extractor]].

При 502 «Could not parse…»: сырой ответ модели — файлы в `storage/extractor-fails/` (папка в репозитории, видна в Cursor без Docker; `*.txt` не в git). В `laravel.log` только `extractor.parse_failed` (`bytes`, `json_error`, `file`). Лор/био: строки в `extraction_runs` нет. Сцена: run остаётся со статусом `failed`, курсор не двигается; авто-job это окно не ставит снова — inbox / «Переразобрать» / ручной POST.

### Таймауты цепочки «Разобрать»

Экстрактор ждёт Ollama до `EXTRACTOR_HTTP_TIMEOUT_SECONDS` (300 с). SPA шлёт `POST /api/extract` с axios `timeout: 320000`; Vite-прокси `/api` — `timeout` / `proxyTimeout` ≥ 320000.

Sail (nginx + PHP-FPM): если `fastcgi_read_timeout` или `max_execution_time` меньше 300 с, запрос оборвётся на прокси раньше Ollama — поднять в образе Sail или задокументировать локальную настройку. Синхронный extract (лор/био/кнопка «Разобрать») очередь не использует. Авторазбор сцен идёт через сервис `queue` (`queue:work --timeout=300`).

## Retrieval

| Переменная | По умолчанию | Описание |
|------------|--------------|----------|
| `RETRIEVAL_STATEMENT_TIMEOUT_MS` | `2000` | `SET LOCAL statement_timeout` для GraphRAG CTE |

Конфиг: `config/retrieval.php`. См. [[Architecture/Retrieval]].

## Context

| Переменная | По умолчанию | Описание |
|------------|--------------|----------|
| `CONTEXT_CHARACTERS_PER_TOKEN` | `3` | Unicode-символов на один оценочный токен |
| `CONTEXT_COPILOT_MAX_INPUT_TOKENS` | `12000` | Бюджет system + секции **реплики** (ключ не менять) |
| `CONTEXT_TOPIC_MAX_INPUT_TOKENS` | `8000` | Бюджет вызова топиков |

Два отдельных запроса к Ollama: 8000+384 и 12000+3000 по отдельности в окне 16384. Не складывать входы. Per-section min/max — только `config/context.php` (`assembler.sections`), без отдельных env. Конфиг: `config/context.php`, `config/ollama.php`, `config/copilot.php`. См. [[Architecture/Context]].

## Хранение сессий, кеша и очереди

| Переменная | По умолчанию | Описание |
|------------|--------------|----------|
| `SESSION_DRIVER` | `database` | Сессии Laravel в PostgreSQL, не в Redis |
| `CACHE_STORE` | `database` | Кеш в PostgreSQL, не в Redis |
| `QUEUE_CONNECTION` | `database` | Очередь в таблице `jobs`; нужна для `RunSceneExtractionJob` (авторазбор сцен). При `RAG_INDEX_SYNC=true` worker всё равно нужен для экстрактора |
| `DB_QUEUE_RETRY_AFTER` | `600` | Повторная выдача database job |

Переменные `REDIS_*` в `.env.example` оставлены как задел. Обычный `docker compose up` Redis не поднимает. Канон мира и чата в Redis не хранится.

## Прочие

| Переменная | Описание |
|------------|----------|
| `APP_PORT` | `8080` — порт Laravel API |
| `FRONTEND_URL` | `http://localhost:5173` |
