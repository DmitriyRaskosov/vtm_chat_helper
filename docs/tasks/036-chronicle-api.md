# 036 — API списка и создания хроник

- **Stage:** 3
- **Status:** done
- **Depends on:** 035
- **Risk:** med
- **Part:** 4
- **Разрешённые зависимости:** нет

## Goal

Авторизованный пользователь видит свои хроники, создаёт новую и сразу становится её рассказчиком, и может открыть хронику, в которой состоит.

## Context

- План §«Этап 3», задача 036. ADR-007 п. 4: хронику создаёт любой пользователь и становится рассказчиком. `users.role` здесь не читать и не писать.
- `ChroniclePolicy` появляется в 038. В этой задаче чужую хронику не отдавать из выборки; `GET` одной чужой — `NotFoundException` (404). Это не закрывает открытый вопрос ADR-007 «403 или 404» для игровых маршрутов (044).
- До 039 `CurrentChronicle` по-прежнему берёт хронику с минимальным `id`. Вторая созданная хроника не станет текущей для `/messages` и `/characters`. Так и задумано, fallback не возвращать.
- Контроллер тонкий: авторизация членством → сервис → Resource (`docs/ARCHITECTURE.md` §1). `abort()` в сервисе не использовать.
- `test_every_api_route_is_listed_in_access_matrix` падает на маршруте без строки. Новые маршруты добавить в матрицу.

## Scope

- `app/Chronicle/ChronicleService.php` (создать)
- `app/Http/Controllers/ChronicleController.php` (создать)
- `app/Http/Requests/StoreChronicleRequest.php` (создать; без `authorize()`)
- `app/Http/Resources/ChronicleResource.php` (создать)
- `app/Models/Chronicle.php` — связь `members()`, если её не добавила 035
- `routes/api.php` — три маршрута внутри `auth:sanctum`
- `tests/Feature/ChronicleTest.php` (создать)
- `tests/Feature/AccessMatrixTest.php`, `tests/Support/AccessMatrixFixtures.php` — строки новых маршрутов

**Out of scope:**
- приглашения (037);
- перевод старых маршрутов под `/chronicles/{chronicle}` (039);
- `ChroniclePolicy` и смена `isStoryteller()` (038);
- фронт (042a).

## Steps

1. `StoreChronicleRequest`: `title` обязателен, строка, max 120; `description` nullable; `setting` nullable, max 120. Лишние поля не принимать.
2. `ChronicleService::create(User, …)` в одной транзакции: строка `chronicles` (`created_by` = пользователь, `status` active) и `chronicle_members` (`role` storyteller, `joined_at` now, `left_at` null).
3. `ChronicleService::listFor(User)` — хроники, где у пользователя `left_at` NULL. `show(User, Chronicle)` — та же проверка, иначе `NotFoundException`.
4. Маршруты:
   - `GET /api/chronicles` → `{ "chronicles": [ { id, title, description, setting, status, role, joined_at } ] }`;
   - `POST /api/chronicles` → 201 `{ "chronicle": { … } }`, `role` = `storyteller`;
   - `GET /api/chronicles/{chronicle}` → `{ "chronicle": { … } }` для участника, 404 для не-участника.
5. `ChronicleTest`: гость на всех трёх → 401; создатель видит хронику в списке и в `GET`; второй пользователь не видит её в списке и получает 404 на `GET`; создатель имеет членство storyteller. В матрицу доступа — те же три маршрута (гость 401; участник не 401/403; не-участник на `GET {chronicle}` — 404).

## Definition of Done

- [ ] три маршрута из плана есть, других маршрутов хроники нет
- [ ] создатель — строка `chronicle_members` с ролью `storyteller` и `left_at` NULL
- [ ] `users.role` эта задача не пишет (`rg "UserRole" app/Chronicle app/Http/Controllers/ChronicleController.php` → пусто)
- [ ] `test_every_api_route_is_listed_in_access_matrix` зелёный
- [ ] `sail pint --test` — чисто
- [ ] `sail php vendor/bin/phpstan analyse` — без новых ошибок; baseline не вырос. В отчёте `baseline: <до> → <после>`
- [ ] `sail artisan test` — зелёные. В отчёте `tests: <до> → <после>`
- [ ] `sail artisan migrate:fresh --seed` — проходит

## Report

Формат — `.cursor/rules/task-execution.mdc` §5. Дополнительно: форма JSON трёх ответов; код чужой хроники (ожидается 404).
