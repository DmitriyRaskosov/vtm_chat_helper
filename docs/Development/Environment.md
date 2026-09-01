# Переменные окружения

Ключевые переменные для RAG, Ollama и copilot (см. `.env.example`).

## RAG и Ollama

| Переменная | По умолчанию | Описание |
|------------|--------------|----------|
| `RAG_EMBEDDING_DIMENSIONS` | `768` | Размер вектора эмбеддинга |
| `RAG_EMBEDDING_DRIVER` | `ollama` | `ollama` в dev; в тестах `stub` (см. [[Development/Testing]]) |
| `RAG_INDEX_SYNC` | `true` | `true` — индексация RAG синхронно после сообщения; `false` — через очередь (`queue:work`) |
| `OLLAMA_URL` | `http://ollama:11434` | URL Ollama из контейнера Laravel |
| `RAG_EMBEDDING_MODEL` | `nomic-embed-text` | Модель эмбеддингов |
| `OLLAMA_CHAT_MODEL` | `qwen3:8b` | Модель генерации черновиков copilot |

## Copilot (опционально)

| Переменная | По умолчанию | Описание |
|------------|--------------|----------|
| `COPILOT_HISTORY_LIMIT` | `30` | Сколько последних сообщений в контекст промпта |
| `COPILOT_RAG_LIMIT` | `5` | Сколько RAG-чанков в контекст |
| `COPILOT_DRAFT_COUNT` | `3` | Количество черновиков |

Конфиг: `config/rag.php`, `config/ollama.php`, `config/copilot.php`.

## Context

| Переменная | По умолчанию | Описание |
|------------|--------------|----------|
| `CONTEXT_CHARACTERS_PER_TOKEN` | `3` | Unicode-символов на один оценочный токен |
| `CONTEXT_L0_MAX_TOKENS` | `6000` | Максимум оценочных токенов в будущем L0-окне |
| `CONTEXT_L0_MAX_MESSAGES` | `50` | Максимум целых сообщений в L0-окне |

Конфиг: `config/context.php`. Алгоритм и правило oversized описаны в [[Architecture/Context]].

## Прочие

| Переменная | Описание |
|------------|----------|
| `APP_PORT` | `8080` — порт Laravel API |
| `FRONTEND_URL` | `http://localhost:5173` |
