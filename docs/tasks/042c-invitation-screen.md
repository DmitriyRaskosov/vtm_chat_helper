# 042c — Экран принятия приглашения

- **Stage:** 3
- **Status:** todo
- **Depends on:** 037, 042a
- **Risk:** med
- **Part:** 4
- **Разрешённые зависимости:** нет

## Goal

По ссылке с кодом авторизованный пользователь принимает приглашение и попадает в чат этой хроники, а неверный код показывает ошибку и не создаёт членство.

## Context

- План §«Этап 3», задача 042, третья часть. Бэкенд: `POST /api/invitations/{token}/accept` (037). Успех — членство. 404 если кода нет. 409 если срок, отзыв, лимит или пользователь уже участник (`InvitationUnavailableException`).
- Гость на этом URL: сначала логин, затем возврат на тот же путь. `meta.guest` не ставить. Хранить намерение возврата так, чтобы логин из 034b не затирал его: если в query/сессии есть приглашение, после логина идти на него, а не всегда на `/chat`. Минимально — query `redirect` на маршрут логина, без нового хранилища.
- После успеха: `useCurrentChronicle.select(id)` и переход на `chat` этой хроники. Id берётся из ответа accept. Если 037 не отдаёт id хроники в теле — остановиться и добавить поле `chronicle_id` в ответ accept (это часть контракта экрана, форма остальных полей не меняется).
- Уже участник (409): не пугать «ошибкой приглашения»; перейти в чат этой хроники, если ответ содержит `chronicle_id`. Если не содержит — показать текст 409 и ссылку на список хроник.
- Строки UI по-русски. Стили — существующие классы, `styles.css` не реорганизовывать.

## Scope

- `frontend/src/composables/useInvitation.js` (создать)
- `frontend/src/views/InvitationAcceptView.vue` (создать)
- `frontend/src/router.js` — `/invitations/:token`, имя `invitation`, `meta.auth: true`
- `frontend/src/views/LoginView.vue` и `frontend/src/composables/useLogin.js` — уважать `redirect` только если это путь `/invitations/...`
- `app/Http/Controllers/ChronicleInvitationController.php` и Resource ответа accept — только если в теле ещё нет `chronicle_id` (добавить поле, остальное не трогать)
- `tests/Feature/ChronicleInvitationTest.php` — поле `chronicle_id` в ответе accept, если его добавили

**Out of scope:**
- создание и отзыв приглашения в UI (API рассказчика есть, экрана в плане нет — не делать);
- роль в шапке (042d);
- почта.

## Steps

1. Проверить JSON accept. Нет `chronicle_id` — добавить и покрыть тестом 037, не меняя коды 409/404.
2. `useInvitation.accept(token)` → POST. View показывает «Принимаем приглашение…», затем чат, либо текст ошибки (`apiErrorMessage`).
3. Маршрут `/invitations/:token`. Гость уходит на логин с возвратом только на `/invitations/:token` (другие `redirect` игнорировать).
4. Успех и кейс «уже участник» выбирают эту хронику через `useCurrentChronicle`.
5. Ручная проверка: гость открывает ссылку → логин → членство и чат; битый код → сообщение, список хроник тот же; отозванный код → текст 409.

## Definition of Done

- [ ] `cd frontend; npm run build` — без ошибок
- [ ] ручная проверка трёх случаев шага 5
- [ ] если менялся PHP: `sail pint --test`, `sail artisan test` (в отчёте `tests: <до> → <после>`), phpstan baseline не вырос, `migrate:fresh --seed`
- [ ] если PHP не менялся — так и написать, прогоны не выдумывать

## Report

Формат — `.cursor/rules/task-execution.mdc` §5. Дополнительно: добавлено ли поле `chronicle_id`; отметки ручной проверки.
