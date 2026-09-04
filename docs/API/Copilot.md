# Copilot API

Контроллер: `CopilotController`. Сервис: `NpcCopilotService`.

## POST /api/copilot/drafts

**Auth:** sanctum + middleware `storyteller`

**Body:**

| Поле | Тип | Правила |
|------|-----|---------|
| `npc_name` | string | required, max 64 |
| `prompt` | string | required, max 2000 |
| `scene_id` | integer | optional; должна быть активной сценой |

**Response 200:**

```json
{
  "copilot_request_id": 42,
  "drafts": ["первый черновик", "второй черновик", "третий черновик"]
}
```

Количество черновиков — `config('copilot.draft_count')` (по умолчанию 3).
`copilot_request_id` идентифицирует сохранённый успешный вызов и передаётся при отправке выбранного черновика через [[API/Messages]].

## Ошибки

| Код | Условие |
|-----|---------|
| 403 | player вызывает эндпоинт |
| 409 | нет активной сцены или передана неактивная сцена |
| 422 | валидация |
| 503 | Ollama недоступна |
| 502 | не удалось распарсить ответ модели |

## Контекст и промпт

- `ContextBuilder` собирает system prompt, запрос рассказчика, raw hot tail активной сцены, релевантные summaries, RAG-чанки типа `message` и низкоприоритетную память намерений рассказчика.
- Общий входной бюджет — `CONTEXT_COPILOT_MAX_INPUT_TOKENS` (12000).
- Приоритет: обязательные инструкции → запрос рассказчика → самые свежие raw messages → summary RAG → message RAG → storyteller intent.
- Message RAG исключается при совпадении с raw history; summary исключается при пересечении с raw диапазоном или другим включённым summary.
- Если модели не хватает фактов, она может вызвать session-scoped tools (`search_messages`, `get_message_range`, `search_summaries`) с лимитом итераций; см. [[Architecture/Retrieval]].
- Scope пассивного RAG пока остаётся глобальным. Отдельный будущий этап введёт явные профили сцены, сессии и global.

Контекст: `COPILOT_HISTORY_LIMIT`, `COPILOT_RAG_LIMIT`. Модель: `qwen3:8b` через `OllamaChatProvider`: `num_ctx=16384`, `num_predict=3000`. Tools — тот же `/api/chat` с JSON `tools`.

Бюджет и metadata источников описаны в [[Architecture/Context]].

Файлового лора и карточек НПС в контексте пока нет.

## Отправка в чат

Успешный вызов сохраняется в `copilot_requests`: исходный prompt, drafts, модель, версии prompt/builder и metadata фактически включённых источников. Ошибочный вызов Ollama не сохраняется. После успеха ставится `RefreshStorytellerIntentJob`: rolling summary намерений хранится отдельно и не попадает в world summaries.

Рассказчик выбирает и при необходимости редактирует черновик, затем отправляет его через [[API/Messages]] с `npc_name`, `copilot_request_id` и `copilot_draft_index`. Один запрос можно связать только с одним итоговым сообщением того же рассказчика, НПС и сцены.

См. [[Features/Copilot]].
