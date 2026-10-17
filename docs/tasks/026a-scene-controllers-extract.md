# 026a — Сцены и сессии: логика контроллеров в модуле Scene

- **Stage:** 2
- **Part:** 2
- **Status:** todo
- **Depends on:** 026
- **Risk:** med (инварианты «одна активная сессия/сцена»)
- **Разрешённые зависимости:** нет

## Goal

`SceneController`, `GameSessionController`, `SceneContextController` и `SceneParticipantController` не содержат транзакций, Eloquent-запросов и `abort`: жизненный цикл сессий и сцен выполняют сервисы модуля Scene.

## Context

- `SceneController`: `store`/`activate`/`close` — по `DB::transaction` с `lockForUpdate`, три `abort_unless(..., 409, ...)`:
  - `Scenes can only be added to the active game session.`
  - `Only scenes in the active game session can be activated.`
  - `Only scenes in the active game session can be closed.`
  `close` замораживает контекст и (после 025a) вызывает `DiaryScheduler::sceneClosed`.
- `GameSessionController::store`: транзакция — архивирует активные сессии, закрывает их сцены, `SceneContextService::freezeMany`, создаёт сессию и первую сцену. `active`: запрос активной сессии со сценами.
- `SceneContextController::serialize` и `SceneParticipantController::serialize` делают запросы `canonical_name` и `Character` на каждый элемент (после 023 — через связь).
- PostgreSQL-инварианты: `game_sessions_single_active`, `game_sessions_one_active_per_chronicle`, `scenes_single_active_per_session`, `scene_participants_current_unique`.
- Baseline: `SceneContextController` (nullsafe), `SceneParticipantController` (`$value` on string) — `debt: 026a`.

## Scope

- `app/Scene/SceneLifecycleService.php` (создать): создание, активация, закрытие сцены
- `app/Scene/GameSessionService.php` (создать): старт новой сессии, текущая активная
- `app/Scene/Exceptions/*`
- `app/Http/Controllers/{Scene,GameSession,SceneContext,SceneParticipant}Controller.php`
- `phpstan-baseline.neon` — удаление закрытых записей
- `tests/Feature/SceneTest.php`, `tests/Feature/GameSessionTest.php` (создать)

**Out of scope:** сериализация (Resources — 021); авторизация (`storyteller` middleware — 030b); `SceneContextService`/`SceneParticipantService` — только вызовы, без изменения логики.

## Steps

1. Характеризация: 3 текста 409 выше; `POST /game-sessions` при существующей активной сессии — старая архивирована, её сцены закрыты и контекст заморожен, новая активна с первой сценой; `GET /game-sessions/active` без сессии → `game_session: null`.
2. Перенести транзакции в `SceneLifecycleService` и `GameSessionService` (блокировки сохранить), проверки — доменные исключения с прежними текстами.
3. `close`: после коммита — `DiaryScheduler::sceneClosed`.
4. Контроллеры: зависимости в конструктор; только вызов сервиса и прежний `serialize` (без запросов внутри, данные — eager load).
5. Закрыть записи baseline `debt: 026a`.

## Definition of Done

- [ ] `rg "DB::|::query\(\)|abort" app/Http/Controllers -g "{Scene,GameSession,SceneContext,SceneParticipant}Controller.php"` → пусто
- [ ] тесты шага 1 зелёные, тексты и коды не изменились
- [ ] записи baseline `debt: 026a` удалены
- [ ] ручная проверка: новая сессия, добавление сцены, закрытие сцены в чате
- [ ] `sail artisan test` — зелёные; `sail pint --test` — чисто; phpstan — OK

## Report

Формат — `.cursor/rules/task-execution.mdc` §5.
