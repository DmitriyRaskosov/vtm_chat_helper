# 032 — Фронт: guard рассказчика, глобальный 401, единый isStoryteller

- **Stage:** 2
- **Part:** 3
- **Status:** todo
- **Depends on:** 030b
- **Risk:** low
- **Разрешённые зависимости:** нет

## Goal

Маршруты с `meta.storyteller` недоступны игроку на уровне роутера, ответ 401 от API разлогинивает пользователя глобально, а признак рассказчика вычисляется в одном месте.

## Context

- `frontend/src/router.js:20`: у `/characters/:id/diary` есть `meta: { auth: true, storyteller: true }`, но `router.beforeEach` (строки ~24–40) читает только `auth` и `guest`.
- `frontend/src/auth.js`: один экземпляр axios `api`, есть только `interceptors.request`; глобальной обработки 401 нет.
- `computed(() => auth.user.value?.is_storyteller === true)` объявлен в 4 местах: `App.vue:18`, `views/ChatView.vue:90`, `composables/useCharacterSheet.js:63`, `components/layout/AppNav.vue:32`. Дальше значение передаётся пропсом в `ChatLog`, `SceneToolbar`, `SheetPlaceSection` — пропсы остаются.
- `.cursor/rules/frontend.mdc` `TODO(032)`.

## Scope

- `frontend/src/auth.js` — экспорт `isStoryteller` (computed), `interceptors.response` для 401
- `frontend/src/router.js` — проверка `meta.storyteller`
- `frontend/src/App.vue`, `views/ChatView.vue`, `composables/useCharacterSheet.js`, `components/layout/AppNav.vue` — замена локальных computed
- `.cursor/rules/frontend.mdc` — закрыть `TODO(032)`

**Out of scope:** пропсы `isStoryteller` в дочерних компонентах; стили; backend; новые зависимости.

## Steps

1. `auth.js`: `export const isStoryteller = computed(...)`.
2. Response-интерсептор: статус 401 и запрос не к `/login`/`/register` → очистить токен и пользователя (существующей функцией выхода/сброса), перейти на `login`. Избежать циклического импорта `router` ↔ `auth` (например, колбэк, регистрируемый в `main.js`) — способ описать в отчёте.
3. `router.beforeEach`: `to.meta.storyteller && !isStoryteller.value` → редирект на `chat` (пользователь уже загружен, как в текущей ветке `auth`).
4. Заменить 4 локальных computed импортом.
5. Закрыть `TODO(032)` в `frontend.mdc`.

## Definition of Done

- [ ] `rg "is_storyteller === true" frontend/src` → одно вхождение, `auth.js`
- [ ] `cd frontend; npm run build` — без ошибок и предупреждений
- [ ] ручная проверка: игрок открывает `/characters/1/diary` → редирект в чат; протухший токен (удалить в БД `personal_access_tokens`) → следующий запрос уводит на логин; рассказчик видит дневник
- [ ] `git diff --stat` — только файлы из Scope

## Report

Формат — `.cursor/rules/task-execution.mdc` §5. Дополнительно: как разорван цикл импорта router/auth.
