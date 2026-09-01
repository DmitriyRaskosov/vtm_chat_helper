# Messages API

Контроллер: `ChatController`.

## GET /api/messages

**Auth:** sanctum

**Query:**

| Поле | Правила |
|------|---------|
| `scene_id` | optional integer; по умолчанию активная сцена |
| `after_id` | optional integer; только сообщения с `id > after_id` |

Возвращаются сообщения только выбранной сцены активной игровой сессии. Закрытая сцена доступна для чтения.

**Response 200:**

```json
{
  "messages": [
    {
      "id": 1,
      "scene_id": 1,
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
| `scene_id` | integer | optional; по умолчанию активная сцена |

**Response 201:** `{ "message": { … } }` — та же структура, что в GET.

Запись разрешена только в активную сцену. Для `draft` или `closed` API возвращает **409**.

После сохранения — индексация RAG (`IndexRagMessageJob`). См. [[API/RAG]].

## Реплика от имени НПС

Рассказчик отправляет `npc_name` + `body`. `user_id` — рассказчик (аудит). В чате `author` = имя НПС, `mine` = false.

См. [[Features/Chat]], [[Features/Scenes]], [[Features/Copilot]].
