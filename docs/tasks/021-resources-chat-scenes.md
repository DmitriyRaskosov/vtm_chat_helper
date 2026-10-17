# 021 — Resources для сообщений, сессий, сцен, контекста и участников

- **Stage:** 2
- **Part:** 3
- **Status:** todo
- **Depends on:** 020
- **Risk:** med (чат опрашивает `GET /messages` каждые 3 секунды)
- **Разрешённые зависимости:** нет

## Goal

Ответы маршрутов чата и сцен сериализуются через `JsonResource`, приватные `serialize()` в пяти контроллерах удалены, форма JSON не изменилась.

## Context

- `ChatController::serialize(Message, $characterLookup)`: поля `id, scene_id, body, author, mine, npc_name, author_character_id, is_ooc, is_deleted, created_at` (`H:i` в `config('app.timezone')`). **`is_deleted` есть в ответе, но не в PHPDoc** — тест должен его проверять.
- `mine` и `author` зависят от **зрителя**: сейчас `auth()->id()` и `isPlayableBy($user)` внутри lookup. В Resource `auth()` не используется — зритель передаётся явно.
- `GameSessionController::serialize` (сессия + сцены + `active_scene_id`), `SceneController::serialize`, `SceneContextController::serialize` (`location_name`, `revision`, `frozen_revision`), `SceneParticipantController::serialize` (`character_name`, `character_type`, `user_id`, `role`, …).
- После 023 имена — через связь; после 025b/026a контроллеры тонкие.

## Scope

- `app/Http/Resources/{Message,GameSession,Scene,SceneContext,SceneParticipant}Resource.php` (создать)
- `app/Http/Controllers/{Chat,GameSession,Scene,SceneContext,SceneParticipant}Controller.php` — только сериализация
- `tests/Feature/ChatJsonShapeTest.php`, `SceneJsonShapeTest.php` (создать)

**Out of scope:** изменение полей; авторизация; фронт; Copilot-ответ (022).

## Steps

1. Тесты формы JSON до правок: `GET/POST /messages`, `PATCH /messages/{id}/ooc`, `POST /messages/{id}/restore` (поле `mine` для автора и для другого игрока, `is_deleted`), `GET /game-sessions/active`, `POST /game-sessions`, `POST .../scenes`, `PATCH /scenes/{id}/close`, `GET/PUT /scenes/{id}/context`, `GET/POST /scenes/{id}/participants`.
2. `MessageResource` принимает зрителя явно: конструктор/`additional(['viewer' => ...])`/фабричный метод — выбрать один способ и обосновать. `auth()`, `request()->user()` внутри Resource запрещены.
3. Остальные Resources — с теми же ключами и форматами дат (`toISOString()` где был он).
4. Удалить приватные `serialize` и `characterLookup`/`lookupIsNpcLike`, если их работу взял Resource + eager load.

## Definition of Done

- [ ] тесты шага 1 зелёные до и после без изменений
- [ ] `rg "function serialize|function characterLookup" app/Http/Controllers` → только `DiaryEntryController` (022)
- [ ] `rg "auth\(\)|request\(\)" app/Http/Resources` → пусто
- [ ] число SQL на `GET /messages` не выросло (сравнить с `CharacterNameQueryCountTest`)
- [ ] ручная проверка: чат обновляется, свои сообщения выделены, OOC и удаление работают
- [ ] `sail artisan test` — зелёные; `sail pint --test` — чисто; phpstan — OK, baseline не вырос

## Report

Формат — `.cursor/rules/task-execution.mdc` §5. Дополнительно: способ передачи зрителя в `MessageResource`.
