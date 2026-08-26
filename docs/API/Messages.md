# Messages API

Контроллер: `ChatController`.

## GET /api/messages

**Auth:** sanctum

**Query (опционально):** `after_id` — только сообщения с `id > after_id`

**Response 200:**

```json
{
  "messages": [
    {
      "id": 1,
      "body": "…",
      "author": "Анна",
      "mine": true,
      "npc_name": null,
      "created_at": "14:30"
    }
  ]
}
```

- `author` — `npc_name` если задан, иначе `user.name`
- `mine` — `false` для сообщений с `npc_name`, иначе сравнение с текущим user

## POST /api/messages

**Auth:** sanctum

**Body:**

| Поле | Тип | Правила |
|------|-----|---------|
| `body` | string | required, max 4000 |
| `npc_name` | string | optional, max 64; только [[Project/Roles|рассказчик]], иначе 403 |

**Response 201:** `{ "message": { … } }` — та же структура, что в GET.

После сохранения — индексация RAG (`IndexRagMessageJob`). См. [[API/RAG]].

## Реплика от имени НПС

Рассказчик отправляет `npc_name` + `body`. `user_id` — рассказчик (аудит). В чате `author` = имя НПС, `mine` = false.

См. [[Features/Chat]], [[Features/Copilot]].
