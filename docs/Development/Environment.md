# Переменные окружения

Ключевые переменные для RAG, Ollama и copilot (см. `.env.example`).

## RAG и Ollama

| Переменная | По умолчанию | Описание |
|------------|--------------|----------|
| `RAG_EMBEDDING_DIMENSIONS` | `1024` | Размер вектора эмбеддинга |
| `RAG_EMBEDDING_DRIVER` | `ollama` | `ollama` в dev; в тестах `stub` (см. [[Development/Testing]]) |
| `RAG_INDEX_SYNC` | `true` | `true` — индексация RAG синхронно после сообщения; `false` — через очередь (`queue:work`) |
| `OLLAMA_URL` | `http://ollama:11434` | URL Ollama из контейнера Laravel |
| `RAG_EMBEDDING_MODEL` | `qwen3-embedding:0.6b` | Модель эмбеддингов |
| `OLLAMA_CHAT_MODEL` | `qwen3:8b` | Модель генерации черновиков copilot |
| `OLLAMA_CONTEXT_LENGTH` | `16384` | Размер runtime context window Ollama (`num_ctx`) |
| `OLLAMA_MAX_OUTPUT_TOKENS` | `3000` | Максимум токенов генерации (`num_predict`) |

## Copilot (опционально)

| Переменная | По умолчанию | Описание |
|------------|--------------|----------|
| `COPILOT_HISTORY_LIMIT` | `30` | Сколько последних сообщений в контекст промпта |
| `COPILOT_RAG_LIMIT` | `5` | Сколько RAG-чанков в контекст |
| `COPILOT_DRAFT_COUNT` | `3` | Количество черновиков |
| `COPILOT_TOOLS_ENABLED` | `true` | Tool-call loop Copilot |
| `COPILOT_TOOLS_MAX_ITERATIONS` | `2` | Максимум раундов tools |
| `COPILOT_TOOLS_SEARCH_LIMIT` | `5` | Лимит `search_messages` / `search_summaries` |
| `COPILOT_TOOLS_RANGE_LIMIT` | `20` | Лимит `get_message_range` |

Конфиг: `config/rag.php`, `config/ollama.php`, `config/copilot.php`.

## Context

| Переменная | По умолчанию | Описание |
|------------|--------------|----------|
| `CONTEXT_CHARACTERS_PER_TOKEN` | `3` | Unicode-символов на один оценочный токен |
| `CONTEXT_L0_MAX_TOKENS` | `15000` | Максимум оценочных токенов в будущем L0-окне |
| `CONTEXT_L0_MAX_MESSAGES` | `50` | Максимум целых сообщений в L0-окне |
| `CONTEXT_L1_SUMMARY_COUNT` | `5` | Число последовательных L0 в одном L1 |
| `CONTEXT_SUMMARY_RAG_LIMIT` | `5` | Максимум релевантных summary-чанков для Copilot |
| `CONTEXT_SUMMARY_CONTEXT_LENGTH` | `24576` | `num_ctx` для L0/L1/final/session суммаризации |
| `CONTEXT_SUMMARY_MAX_OUTPUT_TOKENS` | `3000` | `num_predict` для summary |
| `CONTEXT_COPILOT_MAX_INPUT_TOKENS` | `12000` | Бюджет system + prompt + raw history + RAG + intent |
| `CONTEXT_INTENT_REQUEST_LIMIT` | `20` | Сколько последних Copilot prompts входит в rolling intent |
| `CONTEXT_INTENT_CONTEXT_LENGTH` | `8192` | `num_ctx` для intent summary |
| `CONTEXT_INTENT_MAX_OUTPUT_TOKENS` | `400` | `num_predict` для intent summary |

Вход 12000 + ответ до 3000 укладываются в окно 16384 с техническим запасом. Конфиг: `config/context.php`, `config/ollama.php`. Алгоритм и правило oversized описаны в [[Architecture/Context]].

## Прочие

| Переменная | Описание |
|------------|----------|
| `APP_PORT` | `8080` — порт Laravel API |
| `FRONTEND_URL` | `http://localhost:5173` |
| `DB_QUEUE_RETRY_AFTER` | `600` — повторная выдача database job; должна быть больше summary timeout 300 секунд |
