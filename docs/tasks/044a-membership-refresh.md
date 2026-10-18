# 044a — Обновление memberships после создания хроники и приглашения

- **Stage:** 3
- **Status:** done
- **Depends on:** 042d, 042c
- **Risk:** low
- **Part:** 4
- **Разрешённые зависимости:** нет

## Goal

После создания хроники или принятия приглашения `user.memberships` обновляется, и UI сразу видит роль в новой хронике (без F5).

## Context

- 042d сузил `isStoryteller` до `currentChronicleId` + `user.memberships`.
- Но `memberships` приходит из `/api/user` один раз (при логине / монтировании). `POST /api/chronicles` (036) и accept приглашения (037) возвращают `chronicle_id`, но не обновляют `user.memberships`.
- Симптом (подтверждён вручную): storyteller создаёт новую хронику → UI как у игрока до F5. После F5 — storyteller UI. Тот же баг: player принимает приглашение → UI не знает, что он теперь участник (до F5).
- Это не регрессия 042d, а недоделка: 042d сузил формулу, но не добавил обновление `memberships` после мутаций.

## Scope

- `frontend/src/auth.js` — `refreshUser()`: `GET /api/user`, обновить `user.value` (или `memberships`)
- `frontend/src/composables/useCurrentChronicle.js` — в `create()` вызвать `await refreshUser()` до `select(newId)`
- `frontend/src/composables/useInvitation.js` — в `accept()` вызвать `await refreshUser()` до `select(chronicle_id)`, и на 200, и на 409 «already member»

**Out of scope:**
- бэкенд (`memberships` в `/user` уже есть с 041);
- кэширование `memberships` (не нужно, один запрос);
- 044 (матрица).

## Steps

1. `auth.js`: `refreshUser()` — `GET /api/user`, обновить `user.value`.
2. `useCurrentChronicle.create()`: после успешного `POST /api/chronicles` — `await refreshUser()` до `select(newId)`.
3. `useInvitation.accept()`: после 200 или 409 already-member — `await refreshUser()` до `select(chronicle_id)`.
4. Ручная проверка: storyteller создаёт → storyteller UI; player принимает → player UI; F5 не меняет ничего.

## Definition of Done

- [ ] после создания хроники AppNav показывает «рассказчик» (без F5)
- [ ] после accept приглашения AppNav показывает «игрок» (без F5)
- [ ] `cd frontend; npm run build` — без ошибок
- [ ] `sail artisan test` — зелёные (счётчик: 460, не должен уменьшиться). В отчёте `tests: <до> → <после>`
- [ ] ручная проверка: без F5 роль уже верная; F5 ничего не меняет

## Report

Формат — `.cursor/rules/task-execution.mdc` §5. Дополнительно: сколько запросов `/api/user` добавилось; отметки ручной проверки.
