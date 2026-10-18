# 041 — Удаление глобальной роли пользователя

- **Stage:** 3
- **Status:** done
- **Depends on:** 038
- **Risk:** high
- **Part:** 4
- **Разрешённые зависимости:** нет

## Goal

У пользователя больше нет колонки `users.role`, признак рассказчика считается только по членству в хронике, а `/user` отдаёт список членств.

## Context

- План §«Этап 3», задача 041. ADR-007 п. 4: `users.role` удаляется. Хронику создаёт любой (уже 036). Первый зарегистрированный больше не становится рассказчиком.
- Ревью Part 3: до старта `rg "isStoryteller" app frontend/src`. Каждый hit — либо Policy через членство (`isStorytellerIn`), либо место, которое эта задача переводит. Пропущенный глобальный вызов останется дырой, потому что метод удаляется.
- Известные хвосты на момент постановки (сверить grep'ом, список мог устареть): `User::isStoryteller`, `UserResource` (`role`, `is_storyteller`), `RegisterController` (первый пользователь → storyteller), `frontend/src/auth.js`, `frontend/src/router.js`, views и composables, которые импортируют `isStoryteller`.
- Мёртвые файлы (CharacterAccess, EnsureStoryteller, WorldEntityController, WorldRelationController, StoreWorldEntityRequest, UpdateWorldEntityRequest, StoreWorldRelationRequest) удалены в задачах 009, 010, 030a, 030b. Если grep на шаге 1 их всё равно покажет — это кэш индексации, а не живые файлы. Проверь `git ls-files` в терминале, не через @Codebase. Если файлы реально есть — остановись и доложи.
- `UserFactory::storyteller()` сейчас ставит `users.role`. После удаления колонки состояние сломает тесты. Членство в тестах уже создаёт 038 (`joinChronicle`). Состояние фабрики убрать, вызовы `->storyteller()` заменить на `create()`, где они только ставили роль. Сценарии тестов не менять.
- Фронт до 042d: `isStoryteller` = есть активное членство с ролью `storyteller` (любая хроника). Узкая роль выбранной хроники — 042d. Цикл импортов с `useCurrentChronicle` здесь не заводить.
- Текст `RegisterView`: «Первый зарегистрированный становится рассказчиком, остальные — игроками.» заменить на «Хронику можно создать после входа.» Приглашения в этом тексте не обещать (экран — 042c).
- Миграции: править `database/migrations/0001_01_01_000000_create_users_table.php`, новый файл не создавать (ADR-004).

## Scope

- `database/migrations/0001_01_01_000000_create_users_table.php` — убрать колонку `role`
- `app/Models/User.php` — удалить `isStoryteller()`, cast и fillable `role`; оставить `isStorytellerIn`
- `app/Enums/UserRole.php` — удалить, если после grep не используется
- `app/Http/Controllers/Auth/RegisterController.php` — создавать пользователя без роли
- `app/Http/Resources/UserResource.php` — убрать `role` и `is_storyteller`; добавить `memberships`
- `app/Http/Resources/ChronicleMembershipResource.php` (создать) — `chronicle_id`, `chronicle_title`, `role`, `joined_at`; только `left_at` NULL
- `database/factories/UserFactory.php` — убрать `role` и `storyteller()`
- мёртвые файлы из Context — удалить, если не подключены
- `frontend/src/auth.js` — `isStoryteller` из `user.memberships`
- `frontend/src/views/RegisterView.vue` — одна строка текста
- `tests/Feature/AuthTest.php` и каждый файл из grep `storyteller()` / `UserRole` — снять роль, не менять проверяемое поведение сверх JSON пользователя
- `tests/Feature/UserMembershipResourceTest.php` или кейс в `AuthTest` — форма `/user`

**Out of scope:**
- выбор хроники и маршруты UI (042a, 042b);
- сужение `isStoryteller` до текущей хроники (042d);
- `DemoSeeder` (043) — если сидер пишет `role` и эта задача идёт раньше, поправить только присваивание `role`, чтобы `migrate:fresh --seed` и тесты демо не падали; членства демо не проектировать;
- Policies (038), кроме последствий удалённого метода.

## Steps

1. `rg -n "isStoryteller|UserRole|->storyteller\(" app frontend/src database tests`. Таблица: «уже членство» / «править здесь» / «мёртвый файл». Мёртвые файлы проверить через `git ls-files`; если реально на диске — удалить, если нет — игнорировать (кэш).
2. Удалить колонку `role` из миграции пользователей. `migrate:fresh` обязателен, `migrate` поверх старой схемы не поддерживается (ADR-004).
3. `RegisterController`: поля name, login, password. Никакого `UserRole`. Первый и второй пользователь оба без членств, оба не рассказчики.
4. `UserResource`: `id`, `name`, `login`, `memberships` (активные). Без `role` и без `is_storyteller`.
5. `auth.js`: `isStoryteller` истинно, если в `user.memberships` есть элемент `role === 'storyteller'`. Импорты `isStoryteller` во views не размазывать по другим модулям.
6. Переписать `AuthTest`: оба кейса «первый становится рассказчиком / второй игрок» заменить на «регистрация не выдаёт роль; `memberships` пустой». Логин и `/user` отдают тот же Resource.
7. Прогнать grep из DoD и поправить оставшиеся вызовы. Не оставлять обёртку `isStoryteller()`, которая молча читает членства: метод удаляется, чтобы дыру было видно.

## Definition of Done

- [ ] `rg "isStoryteller" app frontend/src` — только `User::isStorytellerIn` и Policies/контроллеры, которые вызывают `isStorytellerIn` (членство). Вызовов `isStoryteller()` нет. Во `frontend/src` символ `isStoryteller` остаётся как computed от `user.memberships`, не от `user.is_storyteller` / `user.role`
- [ ] `rg "UserRole|'role'" app/Models/User.php database/migrations/0001_01_01_000000_create_users_table.php` → пусто
- [ ] `rg "storyteller\(\)" database/factories tests` → пусто
- [ ] регистрация не создаёт `chronicle_members`
- [ ] `sail artisan migrate:fresh --seed` — проходит
- [ ] дамп схемы: колонки `users.role` нет, остальные колонки пользователей на месте
- [ ] `cd frontend; npm run build` — без ошибок
- [ ] `sail pint --test` — чисто
- [ ] `sail php vendor/bin/phpstan analyse` — без новых ошибок; baseline не вырос. В отчёте `baseline: <до> → <после>`
- [ ] `sail artisan test` — зелёные. В отчёте `tests: <до> → <после>`

## Report

Формат — `.cursor/rules/task-execution.mdc` §5. Дополнительно: таблица шага 1 с пометкой, что сделано с каждой строкой; форма JSON `/user`.
