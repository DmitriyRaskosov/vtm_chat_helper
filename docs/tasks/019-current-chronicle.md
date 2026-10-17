# 019 — CurrentChronicle вместо Chronicle::resolveId, без chronicle_id во входе

- **Stage:** 2
- **Part:** 1
- **Status:** done
- **Depends on:** 034a
- **Risk:** med (меняет контракт API: параметр `chronicle_id` перестаёт приниматься)
- **Разрешённые зависимости:** нет

## Goal

Текущая хроника определяется одним сервисом `App\Chronicle\CurrentChronicle`, модель `Chronicle` не вызывает `abort()`, а параметр `chronicle_id` во входе HTTP запрещён.

## Context

- Решение пользователя: `chronicle_id` во входе Chat/Copilot/CharacterSheet удаляется. В этапе 3 хроника придёт из URL, а параметр во входе стал бы обходом Policy.
- `Chronicle::resolveId(?int)` вызывает `abort_if(..., 409, 'There is no available chronicle.')`; fallback — первая хроника по `id`.
- Потребители: `ChatController::resolveScene` (index, store), `CopilotController::drafts`, `CharacterSheetController::index`/`store`, `GameSessionController::active`/`store`.
- Параметр `chronicle_id` во входе: `ChatController::index` (`$request->validate`), `StoreMessageRequest`, `CopilotDraftsRequest`, `GET /characters?chronicle_id=`, `StoreCharacterRequest`, `GameSessionController::active`, `StoreGameSessionRequest`.
- Фронт параметр не передаёт (`rg "chronicle_id" frontend/src` → пусто на 17.10). Тесты передают: `CharacterTest`, `CharacterVisibilityTest` (`?chronicle_id=`), тела `POST /characters`, `SceneTest` (в т. ч. «другая хроника»).
- Базового доменного исключения ещё нет (018 идёт следом).

## Scope

- `app/Chronicle/CurrentChronicle.php` (создать)
- `app/Chronicle/Exceptions/NoChronicleException.php` (создать)
- `app/Models/Chronicle.php` — удалить `resolveId`
- `app/Http/Controllers/{Chat,Copilot,CharacterSheet,GameSession}Controller.php` — только замена `resolveId` и удаление чтения `chronicle_id`
- `app/Http/Requests/{StoreMessage,CopilotDrafts,StoreCharacter,StoreGameSession}Request.php` — правило `chronicle_id`
- `tests/Feature/*.php` — удаление `chronicle_id` из HTTP-запросов, переписывание сценариев «другая хроника»
- `tests/Feature/CurrentChronicleTest.php` (создать)

**Out of scope:** `chronicle_id` как атрибут моделей и колонок (в `create([...])` тестов и сервисов он остаётся); фронт; Policies; multi-chronicle.

## Steps

1. **До правок:** `rg -n "chronicle_id" frontend/src` и `rg -n "chronicle_id" tests` — в отчёт. Найдено во фронте → остановиться и разобрать с пользователем (вероятно баг). Для тестов — список HTTP-вызовов с параметром (не атрибутов моделей).
2. Характеризационный тест текущего поведения: без хроник `GET /game-sessions/active` → 409 `There is no available chronicle.`; при двух хрониках берётся с меньшим `id`.
3. `CurrentChronicle` (`final`, DI-синглтон не обязателен): `id(): int`, `get(): Chronicle`. Fallback как сейчас — первая по `id`. Нет хроник → `NoChronicleException` (сообщение то же). До 018 исключение само отдаёт 409 через метод `render()` (Laravel вызывает его автоматически); в 018 оно переходит на общий маппинг.
4. Заменить все `Chronicle::resolveId(...)` на `CurrentChronicle` (внедрение через конструктор контроллера). Удалить `resolveId`.
5. Во всех FormRequest и `$request->validate` из Context: `'chronicle_id' => ['prohibited']` — явный 422 вместо молчаливого игнорирования. В `ChatController::index` и `GameSessionController::active` — то же правило.
6. Тесты: убрать `chronicle_id` из HTTP-вызовов. Сценарии «объект из другой хроники» строить так: вторая хроника и её сущности создаются напрямую (модели/фабрики/сервисы), запрос идёт в текущую (первую) хронику.
7. Тест: `POST /messages`, `POST /characters`, `POST /copilot/drafts`, `GET /characters?chronicle_id=1` с параметром → 422.

## Definition of Done

- [ ] `rg "resolveId" app tests` → пусто
- [ ] `rg "abort" app/Models` → пусто
- [ ] `rg "'chronicle_id' =>" app/Http` → только `['prohibited']`
- [ ] `rg "chronicle_id" frontend/src` → пусто
- [ ] тесты шагов 2 и 7 зелёные; сценарии «другая хроника» сохранены (число тестов не уменьшилось)
- [ ] `sail artisan test` — зелёные; `sail pint --test` — чисто; phpstan — OK, baseline не вырос

## Report

Формат — `.cursor/rules/task-execution.mdc` §5. Дополнительно: вывод шага 1; список тестов, где переписан сценарий «другая хроника».
