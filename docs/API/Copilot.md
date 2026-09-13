# Copilot API

Контроллер: `CopilotController`. Сервис: `NpcCopilotService`.

## POST /api/copilot/drafts

**Auth:** sanctum + middleware `storyteller`

**Body:**

| Поле | Тип | Правила |
|------|-----|---------|
| `character_id` | integer | required; должен быть NPC той же хроники, что и сцена (иначе 422/409) |
| `prompt` | string | required, max 2000 |
| `scene_id` | integer | optional; должна быть активной сценой |
| `chronicle_id` | integer | optional; смысловой scope хроники. Без `scene_id` выбирает активную сцену этой хроники (по умолчанию служебная) |

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

- `ContextAssembler` (`context-assembler-v1`, prompt `npc-drafts-v6`) собирает system + слои канона до вызова LLM. `ContextBuilder` — фасад.
- Identity, scene (включая `scene_contexts` / current participants), status, prompt, recent messages, 1-hop relations, каноническая биография, затем leftover-бюджет на Memory/World GraphRAG и rules (только grants).
- Общий входной бюджет — `CONTEXT_COPILOT_MAX_INPUT_TOKENS` (12000). Обязательные секции нельзя вытеснить GraphRAG. Сообщение не режется.
- Пассивного message-RAG нет. Старую историю сессии модель добирает tools (`search_messages`, `get_message_range`); GraphRAG в tool-loop не входит. См. [[Architecture/Retrieval]].

Контекст: `COPILOT_HISTORY_LIMIT`. Модель: `qwen3:8b` через `OllamaChatProvider`: `num_ctx=16384`, `num_predict=3000`. Tools — тот же `/api/chat` с JSON `tools`.

Состав слоёв, форма блоков и provenance — [[Architecture/Context]].

## Отправка в чат

Успешный вызов сохраняется в `copilot_requests`: исходный prompt, drafts, модель, версии prompt/builder, metadata источников, `character_id` и snapshot текущего canonical name в `npc_name`. Ошибочный вызов Ollama не сохраняется. Старые строки не backfill.

Рассказчик выбирает и при необходимости редактирует черновик, затем отправляет его через [[API/Messages]] с тем же `character_id`, `copilot_request_id` и `copilot_draft_index`. Один запрос можно связать только с одним итоговым сообщением того же рассказчика, сцены и персонажа.

См. [[Features/Copilot]].
