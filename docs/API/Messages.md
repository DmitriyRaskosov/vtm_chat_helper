# Messages API

Контроллер: `ChatController`.

## GET /api/messages

**Auth:** sanctum

**Query:**

| Поле | Правила |
|------|---------|
| `scene_id` | optional integer; по умолчанию активная сцена |
| `chronicle_id` | optional integer; смысловой scope хроники. Без `scene_id` выбирает активную сцену этой хроники (по умолчанию служебная). Вместе с `scene_id` сцена должна принадлежать активной сессии этой хроники, иначе 409 |
| `after_id` | optional integer; только сообщения с `id > after_id` |

Возвращаются сообщения только выбранной сцены активной игровой сессии. Сцены разных хроник не смешиваются. Закрытая сцена доступна для чтения.

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
      "author_character_id": null,
      "created_at": "14:30"
    }
  ]
}
```

- `author` — живое `canonical_name` персонажа, если задан `author_character_id`; иначе snapshot `npc_name`; иначе `user.name`; если аккаунт удалён (`user_id` null) — «Аноним»
- `mine` — `false` для реплик НПС и для гулей, которыми текущий пользователь не играет; иначе сравнение с текущим user
- `author_character_id` — nullable; оператор отправки по-прежнему `user_id`

## POST /api/messages

**Auth:** sanctum

**Body:**

| Поле | Тип | Правила |
|------|-----|---------|
| `body` | string | required, max 4000 |
| `npc_name` | string | prohibited для новых сообщений; поле остаётся только в response/DB как исторический snapshot |
| `character_id` | integer | optional для обычной реплики, required для реплики персонажа. Для NPC — только рассказчик (403). Для player — только владелец `user_id` (403). Для ghoul — рассказчик или владелец домитора (403). Должен принадлежать хронике сцены, иначе 409 |
| `scene_id` | integer | optional; по умолчанию активная сцена |
| `chronicle_id` | integer | optional; та же семантика, что у GET |
| `copilot_request_id` | integer | optional; ID успешной генерации, только вместе с NPC `character_id` и `copilot_draft_index` |
| `copilot_draft_index` | integer | optional, min 0; индекс выбранного черновика |

**Response 201:** `{ "message": { … } }` — та же структура, что в GET.

Запись разрешена только в активную сцену. Для `draft` или `closed` API возвращает **409**.

После сохранения — индексация RAG (`IndexRagMessageJob`). См. [[API/RAG]].

## Реплика от имени НПС

Рассказчик отправляет `character_id` + `body`. `user_id` — рассказчик (аудит), а backend сохраняет текущее canonical name в `npc_name` как snapshot. В чате `author` следует за каноническим именем персонажа, `mine` = false.

Если реплика создана через Copilot, UI также передаёт `copilot_request_id` и `copilot_draft_index`. API под транзакционной блокировкой проверяет совпадение рассказчика, сцены и `character_id`. Чужой запрос возвращает **403**, несовпадение или повторное использование — **409**; текст можно отредактировать. Старые строки с одним `npc_name` остаются читаемыми и не backfill.

Игрок может передать `character_id` своего PC (1 пользователь = 1 вампир) или гуля этого PC. Без `character_id` обычная реплика по-прежнему авторствуется как `user.name`. Реплика гуля от игрока не пишет snapshot `npc_name` (`mine` = true). Реплика гуля от рассказчика — как НПС: snapshot имени, `mine` = false.

См. [[Features/Chat]], [[Features/Scenes]], [[Features/Copilot]].
