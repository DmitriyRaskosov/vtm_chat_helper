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

Два запроса к Ollama подряд; HTTP-ответ по-прежнему `{ copilot_request_id, drafts }`.

- `NpcCopilotService`: топики (`npc-topics-v1`, вход ≤8000) → поиск GraphRAG/правил по топикам и допуску лора → реплика (`npc-drafts-v7`, вход ≤12000). `ContextAssembler` (`context-assembler-v2`). `ContextBuilder` — фасад (`buildTopics` / `buildReply`).
- Топики: компактная identity этого NPC, сцена, промпт ST, короткий хвост (`COPILOT_TOPIC_HISTORY_LIMIT` = 8). Без био, лора, графа, правил. `num_predict` 384, `temperature` 0.2, без tools.
- Реплика: identity, scene (включая `scene_contexts` / current participants), status, prompt, recent messages, 1-hop relations, каноническая биография, leftover на Memory/World GraphRAG и rules (grants). Разметка `[speech]` / `[canon]` / `[memory]` / `[sheet]` / `[rules]`.
- Бюджет реплики — `CONTEXT_COPILOT_MAX_INPUT_TOKENS` (12000), ключ не менялся. Бюджет топиков — `CONTEXT_TOPIC_MAX_INPUT_TOKENS` (8000). Обязательные секции нельзя вытеснить GraphRAG. Сообщение не режется. Промпт ST при нехватке не выкидывать.
- Пассивного message-RAG нет. Старую историю сессии модель добирает tools (`search_messages`, `get_message_range`) только на проходе реплики; GraphRAG в tool-loop не входит. См. [[Architecture/Retrieval]].

Хвост реплики: `COPILOT_HISTORY_LIMIT`. Модель: `qwen3:8b` через `OllamaChatProvider`: `num_ctx=16384` на оба вызова; реплика `num_predict=3000`. Tools — тот же `/api/chat` с JSON `tools`.

Состав слоёв, форма блоков и provenance — [[Architecture/Context]].

## Отправка в чат

Успешный вызов сохраняется в `copilot_requests`: исходный prompt, drafts, модель, версии prompt/builder, metadata источников, `character_id` и snapshot текущего canonical name в `npc_name`. Ошибочный вызов Ollama не сохраняется. Старые строки не backfill.

Рассказчик выбирает и при необходимости редактирует черновик, затем отправляет его через [[API/Messages]] с тем же `character_id`, `copilot_request_id` и `copilot_draft_index`. Один запрос можно связать только с одним итоговым сообщением того же рассказчика, сцены и персонажа.

См. [[Features/Copilot]].
