# Copilot API

Контроллер: `CopilotController`. Сервис: `NpcCopilotService`.

## POST /api/copilot/drafts

**Auth:** sanctum + middleware `storyteller`

**Body:**

| Поле | Тип | Правила |
|------|-----|---------|
| `npc_name` | string | required, max 64 |
| `prompt` | string | required, max 2000 |

**Response 200:**

```json
{
  "drafts": ["первый черновик", "второй черновик", "третий черновик"]
}
```

Количество черновиков — `config('copilot.draft_count')` (по умолчанию 3).

## Ошибки

| Код | Условие |
|-----|---------|
| 403 | player вызывает эндпоинт |
| 422 | валидация |
| 503 | Ollama недоступна |
| 502 | не удалось распарсить ответ модели |

## Промпт (внутри `NpcCopilotService`)

- **system:** VtM assistant, JSON `{"drafts":[…]}`
- **user:** имя НПС, промпт рассказчика, RAG-чанки (тип `message`), последние N сообщений чата

Контекст: `COPILOT_HISTORY_LIMIT`, `COPILOT_RAG_LIMIT`. Модель: `qwen3:8b` через `OllamaChatProvider`.

Файлового лора и карточек НПС в контексте пока нет.

## Отправка в чат

Черновик не сохраняется автоматически — рассказчик редактирует и шлёт через [[API/Messages]] с `npc_name`.

См. [[Features/Copilot]].
