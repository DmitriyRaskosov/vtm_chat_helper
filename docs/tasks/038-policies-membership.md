# 038 — Policies по роли в хронике

- **Stage:** 3
- **Status:** done
- **Depends on:** 037
- **Risk:** high (ломает всё, что зависело от isStoryteller())
- **Part:** 4
- **Разрешённые зависимости:** нет

## Goal

Проверки рассказчика в Policies смотрят роль в хронике модели, а не глобальный `users.role`, и появляется `ChroniclePolicy`.

## Context

- План §«Этап 3», задача 038. ADR-003 и ADR-007: меняется реализация Policies, не контракт «рассказчик / владелец». Тексты 403 сохранить: `Только рассказчик.`, `You cannot access this character sheet.`, `You cannot edit this message.`, `Copilot request belongs to another storyteller.`
- `User::isStorytellerIn(Chronicle)` добавлен в 035. `User::isStoryteller()` и колонка `users.role` живут до 041. `UserResource`, `RegisterController`, `frontend/src/auth.js` этой задачей не переписывать.
- Хроника для ability с моделью — из модели, не из `CurrentChronicle`:
  - `Character` → `chronicle_id`;
  - `Scene` → `gameSession.chronicle_id`;
  - `GameSession` → `chronicle_id`;
  - `Message` → сцена → сессия → `chronicle_id`;
  - `CharacterDiaryEntry` → `chronicle_id`;
  - `CopilotRequest` → сцена запроса → хроника.
- Без модели (`CharacterPolicy::create`, `GameSessionPolicy::create`) — хроника из `CurrentChronicle`. До 039 это по-прежнему минимальный `id`. Тесты с двумя хрониками: членство должно быть на той хронике, которую вернёт `CurrentChronicle`, иначе рассказчик получит 403. Это не чинить fallback'ом.
- `ScenePolicy::view` и `GameSessionPolicy::view` сейчас `return true` с комментарием «этап 3». Их закрывает 044, здесь не менять.
- `MessagePolicy::create` сейчас `return true`. Не менять: чужой пользователь на коллекции закрывается группой маршрута в 039/044. Авторство правки сообщения остаётся (автор или рассказчик этой хроники).
- `CopilotPolicy::use` не вызывает `isStoryteller()`: владелец запроса (`storyteller_id`) плюс рассказчик хроники этого запроса. Чужой владелец — прежний текст 403.
- `CharacterPolicy::speakAs`: NPC — `isStorytellerIn` хроники персонажа; игрок — `isPlayableBy` (035). Архивный по-прежнему никто.
- `CharacterSheetController::index` фильтрует список через `isStoryteller()`, это не Policy, но тот же глобальный признак. Перевести на `isStorytellerIn` хроники из `CurrentChronicle`. Поиск PC по одному `user_id` без хроники — дыра двух хроник, её закрывает 039.
- Временные `AuthorizationException` в `ChronicleInvitationController` (037) заменить на `ChroniclePolicy`.
- Полный grep по `app/` и `frontend/src` на конец 038 ещё находит `User::isStoryteller`, `UserResource`, `RegisterController`, фронт и мёртвые классы. Это вход задачи 041: перечислить в отчёте, не чинить здесь. Иначе 038 поглотит 041.

## Scope

- `app/Policies/ChroniclePolicy.php` (создать)
- `app/Policies/CharacterPolicy.php`
- `app/Policies/MessagePolicy.php`
- `app/Policies/ScenePolicy.php` — только abilities рассказчика, не `view`
- `app/Policies/GameSessionPolicy.php` — только abilities рассказчика, не `view`
- `app/Policies/CharacterDiaryEntryPolicy.php`
- `app/Policies/CopilotPolicy.php`
- `app/Http/Controllers/CharacterSheetController.php` — условие списка/архива в `index`
- `app/Http/Controllers/ChronicleInvitationController.php` — `authorize` вместо ручной проверки
- `routes/api.php` — `->can(...)` для invite/revoke, если проверка уходит с контроллера на маршрут
- `tests/Support/JoinsChronicle.php` (создать) — trait `joinChronicle(User, Chronicle, ChronicleRole)`
- `tests/Support/AccessMatrixFixtures.php` — членства: storyteller, playerOwner, playerForeign в одной и той же хронике фикстуры (смысл `player_foreign` из 030c не менять)
- `tests/Feature/*AuthorizationTest.php`, `tests/Feature/ChronicleInvitationTest.php`, прочие тесты, которые падают из-за отсутствия членства — только добавить `joinChronicle`, коды и тексты не менять
- `tests/Feature/ChroniclePolicyTest.php` (создать)

**Out of scope:**
- удаление `User::isStoryteller()` и `users.role` (041);
- `ScenePolicy::view` / матрица чужой хроники (044);
- смена URL (039);
- фронт;
- мёртвые `CharacterAccess`, `EnsureStoryteller`, `WorldEntityController`, `StoreWorldEntityRequest`, `UpdateWorldEntityRequest`, `StoreWorldRelationRequest`, `WorldRelationController` — только список в отчёте.

## Steps

1. `rg -n "isStoryteller" app frontend/src` — таблица в отчёт: файл, Policy этой задачи или хвост 041.
2. `ChroniclePolicy`: `view` — активный участник (`left_at` NULL), иначе deny; `create` — любой авторизованный (создание хроники уже в 036, ability на будущее); `update` — `isStorytellerIn`; `createInvitation` и `revokeInvitation` — `isStorytellerIn`, иначе 403. Маршруты 037 перевести на эти ability.
3. В каждой Policy из Scope заменить `isStoryteller()` на `isStorytellerIn($chronicle)` хроники модели. `create` без модели — `CurrentChronicle`. Тексты `Response::deny` не менять. `ScenePolicy::view` и `GameSessionPolicy::view` оставить `true`.
4. `CopilotPolicy::use`: отказ, если пользователь не владелец запроса (прежний текст) или не рассказчик хроники запроса.
5. `CharacterSheetController::index`: ветка «видит всех / видит архив» через `isStorytellerIn` текущей хроники. Запрос PC по голому `user_id` не трогать.
6. Фикстуры и тесты: перед действием рассказчика есть членство storyteller в хронике ресурса; игрок-владелец — членство player. Ожидаемые 403 и тексты из характеризации 030a/030b не менять.
7. `ChroniclePolicyTest`: не-участник не проходит `view`; игрок не создаёт приглашение; рассказчик этой хроники создаёт; рассказчик другой хроники — 403.

## Definition of Done

- [ ] `rg "isStoryteller\(\)" app/Policies` → пусто
- [ ] `rg "function view" app/Policies/ScenePolicy.php` — метод по-прежнему возвращает `true`
- [ ] тексты 403 из Context не изменились; `CharacterVisibilityTest` зелёный
- [ ] `rg "isStoryteller" app frontend/src` — в отчёте каждое вхождение помечено «Policy / User::isStorytellerIn» или «хвост 041». Глобальных проверок в Policies нет. Метод `User::isStoryteller()` ещё есть (его удаляет 041)
- [ ] критерий 041 (полный запрет глобальных проверок во `app/` и `frontend/src`) в этой задаче не закрывается и в DoD не отмечается выполненным
- [ ] `sail pint --test` — чисто
- [ ] `sail php vendor/bin/phpstan analyse` — без новых ошибок; baseline не вырос. В отчёте `baseline: <до> → <после>`
- [ ] `sail artisan test` — зелёные. В отчёте `tests: <до> → <после>`
- [ ] `sail artisan migrate:fresh --seed` — проходит

## Report

Формат — `.cursor/rules/task-execution.mdc` §5. Дополнительно: таблица шага 1; список «ability → откуда взята хроника».
