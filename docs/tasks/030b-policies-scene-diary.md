# 030b — Policies: сессии, сцены, дневник, Copilot; удаление middleware storyteller

- **Stage:** 2
- **Part:** 3
- **Status:** todo
- **Depends on:** 030a
- **Risk:** high (авторизация ~20 маршрутов)
- **Разрешённые зависимости:** нет

## Goal

Все оставшиеся маршруты авторизуются через Policies, middleware `EnsureStoryteller` и все `authorize()` в FormRequest удалены.

## Context

- ADR-003. Группа `Route::middleware('storyteller')` в `routes/api.php`: `POST /game-sessions`, `POST /game-sessions/{gameSession}/scenes`, `PATCH /scenes/{scene}/activate|close`, `PUT /scenes/{scene}/context`, `POST /scenes/{scene}/participants`, `PATCH /scenes/{scene}/participants/{character}`, все маршруты дневника (`/characters/{character}/diary`, `/diary/preview`, `/diary-entries/{diaryEntry}` GET/PATCH/DELETE, `/regenerate`); отдельно `POST /copilot/drafts` → `->middleware('storyteller')`. Текст отказа: `403 Только рассказчик.`
- Открытые для любого авторизованного (так и остаётся в этапе 2; членство — этап 3): `GET /game-sessions/active`, `GET /messages`, `GET /scenes/{scene}/context`, `GET /scenes/{scene}/participants`.
- Оставшиеся `authorize()`: `UpdateDiaryEntryRequest` (`isStoryteller`), `PreviewDiaryRequest`, `StoreSceneRequest`, `StoreGameSessionRequest`, `StoreSceneParticipantRequest`, `UpdateSceneContextRequest`, `CopilotDraftsRequest`, `Auth/LoginRequest`, `Auth/RegisterRequest` (последние — `true`).
- Алиас `storyteller` — `bootstrap/app.php`.

## Scope

- `app/Policies/{GameSession,Scene,CharacterDiaryEntry}Policy.php` (создать); `CharacterPolicy` — ability `viewDiary`, `previewDiary`; `CopilotRequestPolicy` — `create`
- `routes/api.php` — замена `storyteller` на `->can(...)`
- `app/Http/Middleware/EnsureStoryteller.php` (удалить), `bootstrap/app.php` — удалить алиас
- `app/Http/Requests/**/*.php` — удалить все `authorize()`
- `app/Http/Controllers/*` — только вызовы `Gate::authorize`, если ability нельзя выразить на маршруте
- `app/Http/Controllers/CopilotController.php` — `abort_if` (пустое имя NPC, 422) заменить на `InvalidInputException` или валидацию в `CopilotDraftsRequest`. Убрать последний `abort` из контроллера.
- `tests/Feature/*AuthorizationTest.php`

**Out of scope:** маршруты, открытые всем авторизованным (Policy `view` = `true` с комментарием `// этап 3: членство в хронике`); `ChroniclePolicy` (нет маршрутов хроники — этап 3); фронт.

## Steps

1. Характеризация: на каждый маршрут из группы `storyteller` и `POST /copilot/drafts` — игрок → 403 `Только рассказчик.`, рассказчик → не 403.
2. Policies с `Response::deny('Только рассказчик.')` для действий рассказчика.
3. Маршруты: `->can('close', 'scene')`, `->can('create', GameSession::class)` и т. д.; группу `storyteller` убрать.
4. Удалить `EnsureStoryteller` и алиас; удалить все `authorize()` в FormRequest.
5. CopilotController::drafts: abort_if (пустое имя NPC) — заменить 
  на InvalidInputException (422) или валидацию в FormRequest. 
  После 030b grep "abort" app/Http/Controllers/CopilotController 
  должен быть пустым.

## Definition of Done

- [ ] `rg "storyteller" routes bootstrap` → пусто
- [ ] `rg 'abort' app/Http/Controllers/CopilotController.php` → пусто
- [ ] `rg "function authorize" app/Http/Requests` → пусто
- [ ] `rg "isStoryteller\(\)" app --glob "!app/Policies/**" --glob "!app/Models/User.php"` → пусто или только `CharacterVisibility`
- [ ] тесты шага 1 зелёные, тексты 403 не изменились
- [ ] ручная проверка под игроком: экран дневника недоступен, чат работает
- [ ] `sail artisan test` — зелёные; `sail pint --test` — чисто; phpstan — OK, baseline не вырос

## Report

Формат — `.cursor/rules/task-execution.mdc` §5. Дополнительно: таблица «маршрут → ability».
