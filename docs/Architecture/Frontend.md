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
- `ChatView.vue` — сценовый чат + управление сценами + панель рассказчика

## Панель рассказчика (`ChatView.vue`)

Показывать только если `user.is_storyteller`. Двухколоночный layout: чат + `aside.storyteller-panel`.

Над лентой все пользователи видят активную игровую сессию и selector её сцен. Переключение очищает локальную ленту и загружает сообщения с `scene_id`. Закрытая сцена read-only. Если активной сессии нет, рассказчик получает форму её создания, а игрок — состояние ожидания.

Рассказчик дополнительно может создать и активировать сцену, активировать `draft` и закрыть активную. См. [[Features/Scenes]].

Copilot flow:

1. Поля `npc_name`, ситуационный `prompt`
2. `POST /api/copilot/drafts` — три черновика и `copilot_request_id`; loading и ошибки API
3. Выбор черновика, правка в textarea
4. `POST /api/messages` с `{ body, npc_name, scene_id, copilot_request_id, copilot_draft_index }` — отправка и трассировка выбранного результата

При смене сцены сохранённый ID и черновики сбрасываются. После успешной отправки повторное использование той же генерации в UI невозможно.

Игроки видят сцены, ленту и свой composer; управление сценами и панель copilot не рендерить.

Подробнее: [[Features/Copilot]], [[API/Copilot]].
