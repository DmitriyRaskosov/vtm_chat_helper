# Игровые сессии и сцены API

Игровая история организована как `GameSession → Scene → Message`. Одновременно активны не более одной игровой сессии и одной сцены внутри неё.

## GET /api/game-sessions/active

**Auth:** sanctum, любая роль.

Возвращает активную игровую сессию, все её сцены и `active_scene_id`. Закрытые сцены остаются в списке и доступны для чтения.

## POST /api/game-sessions

**Auth:** sanctum + `storyteller`.

Body: `title` — required string, max 120.

Создаёт и активирует новую сессию с начальной активной сценой. Предыдущая активная сессия архивируется, а её активная сцена закрывается.

## POST /api/game-sessions/{gameSession}/scenes

**Auth:** sanctum + `storyteller`.

| Поле | Правила |
|------|---------|
| `title` | required string, max 120 |
| `description` | optional string, max 2000 |
| `activate` | optional boolean, default `true` |

Активированная новая сцена переводит предыдущую активную сцену в `draft`, чтобы к ней можно было вернуться.

## PATCH /api/scenes/{scene}/activate

**Auth:** sanctum + `storyteller`.

Активирует `draft`-сцену текущей активной сессии. Закрытую сцену активировать нельзя.

## PATCH /api/scenes/{scene}/close

**Auth:** sanctum + `storyteller`.

Закрывает сцену. Сообщения остаются доступны через [[API/Messages]], но новые сообщения и Copilot для закрытой сцены запрещены.

См. [[Features/Scenes]], [[Project/Roles]].
