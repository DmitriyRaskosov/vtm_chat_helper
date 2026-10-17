# 030c — Матрица доступа «роль × маршрут»

- **Stage:** 2
- **Part:** 3
- **Status:** done
- **Depends on:** 030b, 034a
- **Risk:** low
- **Разрешённые зависимости:** нет

## Goal

Каждый маршрут `routes/api.php` покрыт тестом доступа для гостя, игрока-владельца, постороннего игрока и рассказчика, а новый маршрут без строки в матрице роняет тест.

## Context

- ADR-003 п. 5. Policies — после 030a/030b.
- Фабрики из 034a: User (`storyteller()`), Chronicle, GameSession, Scene, SceneParticipant, Character (`npc()`, `player()`), Message.
- Ожидаемые коды: гость → 401; запрещено → 403; разрешено → не 401/403 (конкретный 2xx/4xx валидации не важен).
- `.cursor/rules/testing.mdc` `TODO(030)` — закрывается этой задачей.

## Scope

- `tests/Feature/AccessMatrixTest.php` (создать)
- `tests/Support/AccessMatrixFixtures.php` (создать) — сцена, NPC, персонаж игрока, сообщение, запись дневника, `CopilotRequest`
- `.cursor/rules/testing.mdc` — закрыть `TODO(030)`, шаблон матрицы

**Out of scope:** `app/` (найденные дыры — `markTestSkipped('bug: …')` + отчёт, исправления — отдельной задачей); чужая хроника (этап 3, задача 044).

## Steps

1. Матрица как `dataProvider`: `[метод, URI-шаблон, роль, ожидаемый класс ответа]` для всех маршрутов `api.php` (около 35) × 4 роли.
2. Фикстуры через фабрики; подстановка `{scene}`, `{character}`, `{message}`, `{diaryEntry}`, `{gameSession}`.
3. Тест-страж: перечислить `Route::getRoutes()` с префиксом `api/` и проверить, что каждый `метод + URI` присутствует в матрице.
4. Закрыть `TODO(030)` в `testing.mdc`.

## Definition of Done

- [ ] все маршруты `api.php` есть в матрице (тест-страж зелёный)
- [ ] `git diff app/ routes/` → пусто
- [ ] `sail artisan test` — зелёные (skipped — только с описанием бага в отчёте); `sail pint --test` — чисто

## Report

Формат — `.cursor/rules/task-execution.mdc` §5. Дополнительно: таблица матрицы (маршрут × роль → код); найденные дыры.
