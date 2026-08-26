# Frontend

Vue 3 SPA в `frontend/`. Запуск: `cd frontend && npm run dev` (порт 5173 на хосте).

## Структура

| Путь | Назначение |
|------|------------|
| `frontend/src/views/` | Экраны (SFC) |
| `frontend/src/router.js` | Маршруты |
| `frontend/src/auth.js` | Axios `api` (`baseURL: '/api'`), токен в `localStorage` |

Не ходить на Laravel web-URL и не подключать Blade.

## Экраны

- `LoginView.vue`, `RegisterView.vue` — auth
- `ChatView.vue` — общий чат + панель рассказчика

## Панель рассказчика (`ChatView.vue`)

Показывать только если `user.is_storyteller`. Двухколоночный layout: чат + `aside.storyteller-panel`.

Copilot flow:

1. Поля `npc_name`, ситуационный `prompt`
2. `POST /api/copilot/drafts` — три черновика; loading и ошибки API
3. Выбор черновика, правка в textarea
4. `POST /api/messages` с `{ body, npc_name }` — отправка в общий чат

Игроки видят только основной чат и свой composer; панель copilot не рендерить.

Подробнее: [[Features/Copilot]], [[API/Copilot]].
