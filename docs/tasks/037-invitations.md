# 037 — Приглашения в хронику

- **Stage:** 3
- **Status:** done
- **Depends on:** 036
- **Risk:** med
- **Part:** 4
- **Разрешённые зависимости:** нет

## Goal

Рассказчик создаёт и отзывает приглашение по коду, любой авторизованный пользователь принимает непросроченный код в пределах лимита, а просроченное, отозванное и исчерпанное приглашение не даёт членства. Успешный accept возвращает `chronicle_id`, чтобы фронт (042c) мог выбрать эту хронику и перейти в чат.

## Context

- План §«Этап 3», задача 037. ADR-007 п. 2: вступление по ссылке-коду, без почты. Колонки заданы в 035: `token_hash`, `role`, `created_by`, `expires_at`, `max_uses`, `uses`, `revoked_at`.
- В базе хранится только хеш. Открытый код возвращается один раз в ответе создания.
- `unique(chronicle_id, user_id)` не позволяет вторую строку членства. Повторное принятие после `left_at` обновляет ту же строку (`left_at` = NULL, `joined_at` = now), а не вставляет новую. Эндпоинта «выйти» в плане нет — не добавлять.
- `ChroniclePolicy` ещё нет (038). Отказ не-рассказчику — `AuthorizationException` в контроллере (403), не `abort()` и не исключение сервиса. В 038 эти две проверки переезжают в Policy.
- Проверки срока, лимита и отзыва — доменные, `ConflictException` (409), тексты зафиксировать тестом.
- `max_uses` NULL — без лимита. Это чтение nullable-колонки из плана, не отдельное продуктовое правило; если при реализации окажется спорным — остановиться.
- Уже активный участник (`left_at` NULL): 409, `uses` не увеличивать.
- Открытые вопросы ADR-007 (несколько рассказчиков, судьба персонажа при `left_at`) не решать. Роль в приглашении может быть `storyteller` или `player` — схема это уже позволяет.
- 042c зависит от `chronicle_id` в ответе accept. Без него фронт не знает, в какую хронику переходить. Поле нужно и на 200 (принято), и на 409 «уже участник» — фронт должен предложить перейти в чат этой хроники, а не показывать ошибку.

## Scope

- `app/Chronicle/InvitationService.php` (создать)
- `app/Chronicle/Exceptions/InvitationUnavailableException.php` (создать, `extends ConflictException`) — срок, отзыв, лимит, уже участник; один класс, разные сообщения
- `app/Http/Controllers/ChronicleInvitationController.php` (создать)
- `app/Http/Requests/StoreChronicleInvitationRequest.php` (создать; без `authorize()`)
- `app/Http/Resources/ChronicleInvitationResource.php` (создать; без хеша и без открытого кода)
- `routes/api.php`
- `tests/Feature/ChronicleInvitationTest.php` (создать) — тесты на `chronicle_id` в 200 и 409
- `tests/Feature/AccessMatrixTest.php` — строки новых маршрутов

**Out of scope:**
- `ChroniclePolicy` (038) — в контроллере временная проверка `isStorytellerIn`;
- письмо, одноразовые ссылки помимо `max_uses`;
- экран фронта (042c);
- удаление `users.role`.

## Steps

1. `StoreChronicleInvitationRequest`: `role` — `Rule::enum(ChronicleRole::class)`; `expires_at` — дата в будущем; `max_uses` — nullable integer, min 1.
2. `InvitationService::create`: 32 случайных байта, код url-safe; в БД `hash('sha256', $plain)`; `created_by` = текущий пользователь; `uses` = 0; `revoked_at` NULL. Ответ контроллера: Resource плюс поле `token` (открытый код) только в этом ответе.
3. `revoke`: приглашение этой хроники, `revoked_at` = now. Чужая хроника — 404 (`NotFoundException`), как `GET` хроники в 036.
4. `accept`: найти по хешу. 404, если кода нет. 409, если `revoked_at` не NULL, `expires_at` в прошлом, `max_uses` не NULL и `uses` >= `max_uses`, или пользователь уже активный участник. Иначе в одной транзакции: `uses++` и членство (вставка или сброс `left_at`) с ролью приглашения.
5. Ответ 200: `{ chronicle_id: <id>, role: 'player', joined_at: <ISO> }`. Ответ 409 «уже участник»: `{ chronicle_id: <id>, message: '...' }` — чтобы фронт мог перейти в чат. Если пользователь уже участник, его членство не пересоздаётся, `chronicle_id` берётся из приглашения (или из существующего членства — реши, что надёжнее при отозванном приглашении; если приглашение отозвано и пользователь уже участник — всё равно вернуть `chronicle_id` из членства).
6. Маршруты под `auth:sanctum`:
   - `POST /api/chronicles/{chronicle}/invitations` — только `isStorytellerIn($chronicle)`, иначе 403;
   - `DELETE /api/chronicles/{chronicle}/invitations/{invitation}` — то же;
   - `POST /api/invitations/{token}/accept` — любой авторизованный. `{token}` не вкладывать в `{chronicle}`: код сам указывает хронику.
7. Тесты: рассказчик создаёт и отзывает; игрок этой хроники на create/revoke → 403; гость → 401; принятие → членство и `uses` = 1; просрочка, отзыв, лимит, повтор активного участника → 409 и членство не появляется / не дублируется; принятие после `left_at` реактивирует ту же строку. Матрица доступа содержит все три маршрута.

## Definition of Done

- [ ] открытый код не пишется в `token_hash` и не возвращается из revoke/accept
- [ ] `POST /invitations/{token}/accept` на 200 возвращает `chronicle_id` (int).
- [ ] на 409 «уже участник» возвращает `chronicle_id` (int).
- [ ] тест: успешный accept → `chronicle_id` совпадает с хроникой приглашения.
- [ ] тест: повторный accept уже участником → 409 с `chronicle_id` той же хроники.
- [ ] четыре отказа (срок, отзыв, лимит, уже участник) дают 409 и покрыты тестом
- [ ] `rg "Mail::|Notification::" app/Chronicle` → пусто
- [ ] `test_every_api_route_is_listed_in_access_matrix` зелёный
- [ ] `sail pint --test` — чисто
- [ ] `sail php vendor/bin/phpstan analyse` — без новых ошибок; baseline не вырос. В отчёте `baseline: <до> → <после>`
- [ ] `sail artisan test` — зелёные. В отчёте `tests: <до> → <после>`
- [ ] `sail artisan migrate:fresh --seed` — проходит

## Report

Формат — `.cursor/rules/task-execution.mdc` §5. Дополнительно: тексты 409; что сделано при повторном accept после `left_at`.
