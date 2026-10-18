# 042a — Выбор текущей хроники

- **Stage:** 3
- **Status:** done
- **Depends on:** 036, 041
- **Risk:** med
- **Part:** 4
- **Разрешённые зависимости:** нет

## Goal

Пользователь видит свои хроники, создаёт новую и выбирает текущую без Pinia; выбранный id сохраняется в браузере. В AppNav — dropdown с названием текущей хроники и списком других + «создать новую». Не только ссылка «Хроники».

## Context

- План §«Этап 3», задача 042, первая часть. ADR-007 п. 6: выбор хроники, composable `useCurrentChronicle`, без Pinia (`docs/ARCHITECTURE.md` §8).
- API уже есть (036): `GET /api/chronicles`, `POST /api/chronicles`, `GET /api/chronicles/{chronicle}`. Поля — как в `ChronicleResource` задачи 036.
- `UserResource.memberships` есть с 041. Роль в шапке по выбранной хронике — 042d. Здесь достаточно списка и выбора id.
- Маршруты чата с `:chronicleId` — 042b. Этот экран живёт отдельно, чтобы выбор был готов до перешивки чата.
- Глобальный `styles.css` не реорганизовывать. Классы `top`, `card`, `muted`, `error` уже есть.
- Composable не вызывает `onMounted`; загрузку списка запускает view.

## Scope

- `frontend/src/composables/useCurrentChronicle.js` (создать)
- `frontend/src/views/ChronicleListView.vue` (создать)
- `frontend/src/components/layout/ChronicleSwitcher.vue` (создать) — dropdown: текущая хроника, список остальных, «создать новую»
- `frontend/src/router.js` — маршрут `/chronicles` (имя `chronicles`), `meta.auth: true`
- `frontend/src/components/layout/AppNav.vue` — `ChronicleSwitcher` вместо ссылки «Хроники»

**Out of scope:**
- префикс `/c/:chronicleId` у чата и персонажей (042b);
- экран приглашения (042c);
- пересчёт `isStoryteller` от выбранной хроники (042d);
- бэкенд.

## Steps

1. `useCurrentChronicle`: `chronicles`, `currentId`, `loading`, `error`, `load()`, `select(id)`, `create({ title, description, setting })`. HTTP только через `api` из `auth.js`. `currentId` в `localStorage` под ключом `game-chat-chronicle`. Если сохранённого id нет в списке — сбросить.
2. `ChronicleListView`: список (название, роль), кнопка выбора, форма создания (название обязано). Ошибки через `apiErrorMessage`. Пустой список — текст «Хроник пока нет» и форма. После создания хроника выбрана.
3. Маршрут `/chronicles`. Редирект с `/` не менять (его перенесёт 042b): пока `/` → `/chat`.
4. `ChronicleSwitcher.vue`: в `AppNav` заменить ссылку «Хроники» на dropdown. Клик по хронике → `router.push(\`/c/${id}/chat\`)`. Клик «создать» → переход на `/chronicles` (или модалка создания).
5. Pinia не добавлять. Новый axios не создавать.

## Definition of Done

- [ ] `rg "pinia|createPinia" frontend` → пусто
- [ ] `rg "axios" frontend/src/composables/useCurrentChronicle.js frontend/src/views/ChronicleListView.vue` → пусто (только `api` из `auth.js`, и только в composable)
- [ ] `cd frontend; npm run build` — без ошибок
- [ ] `ChronicleSwitcher` в `AppNav` показывает текущую хронику по названию (не только id)
- [ ] смена хроники через dropdown → `/c/{newId}/chat`
- [ ] ручная проверка: пустой список; создание хроники показывает её в списке после обновления страницы; выбор пишет id в `localStorage` и восстанавливается после перезагрузки; чужая хроника в списке не появляется
- [ ] `sail pint --test` — не требуется, PHP не менялся; если файлы PHP не в diff, так и написать
- [ ] `sail artisan test` — не требуется (фронт). В отчёте указать «не запускался, бэкенд не менялся»
- [ ] phpstan — не требуется
- [ ] `sail artisan migrate:fresh --seed` — не требуется

## Report

Формат — `.cursor/rules/task-execution.mdc` §5. Дополнительно: ключ `localStorage`; отметки ручной проверки.
