# Frontend

Vue 3 SPA в `frontend/`. Запуск: `cd frontend && npm run dev` (порт 5173 на хосте).

## Структура

| Путь | Назначение |
|------|------------|
| `frontend/src/views/` | Оболочки маршрутов: `AppNav`, табы/`error`, проброс в components. Каждая ~150 строк |
| `frontend/src/components/layout/` | `AppNav.vue` — `Чат · Персонажи · Мир`, logout |
| `frontend/src/components/chat/` | `SceneToolbar`, `ChatLog`, `ChatComposer`, `StorytellerCopilotPanel` |
| `frontend/src/components/world/` | `WorldDirectoryTab`, `WorldPoliticsSection`, `WorldLoreTab` |
| `frontend/src/components/sheet/` | `TraitDots`, `SheetPlayBar`, секции листа, `SheetPlaceSection` |
| `frontend/src/composables/` | `useSceneSession`, `useChatMessages`, `useWorld*`, `useCharacterSheet` |
| `frontend/src/router.js` | Маршруты |
| `frontend/src/auth.js` | Axios `api` (`baseURL: '/api'`), токен в `localStorage` |

UI-only правки: briefs в `docs/Agent/` (`ui-chat.md`, `ui-character-list.md`, `ui-character-sheet.md`, `ui-world.md`). Не Pinia, не CSS-modules.

Не ходить на Laravel web-URL и не подключать Blade.

## Экраны

- `LoginView.vue`, `RegisterView.vue` — auth (без AppNav)
- `ChatView.vue` — оболочка чата: poll, смена сцены, сброс copilot
- `CharacterListView.vue` — дерево листов, создание PC/NPC/гуля, скрытие/возврат (ST)
- `CharacterSheetView.vue` — оболочка листа; гуль compact; секции в `components/sheet/`
- `WorldView.vue` — оболочка `/world`: таб, `loadBase()`, рендер directory/lore

Маршруты `/characters` и `/characters/:id` (`meta.auth`); `/world` (`meta.auth` + `storyteller`). Широкий layout на листах у всех ролей и на `/world` у ST. Copilot на листах нет. Редактора памяти нет. Подробнее: [[Features/Characters]], [[Features/World]], [[API/Characters]], [[API/World]], [[API/Lore]].

## Панель рассказчика (`StorytellerCopilotPanel.vue`)

Показывать только если `user.is_storyteller`. Двухколоночный layout: чат + `aside.storyteller-panel`.

Над лентой все пользователи видят активную игровую сессию служебной хроники (`GET /api/game-sessions/active` без `chronicle_id`) и selector её сцен. Переключение очищает локальную ленту и загружает сообщения с `scene_id`. Закрытая сцена read-only. Если активной сессии нет, рассказчик получает форму её создания, а игрок — состояние ожидания. Отдельного UI выбора хроники нет.

Рассказчик дополнительно может создать и активировать сцену, активировать `draft` и закрыть активную, а также добавить/убрать персонажей в блоке «На сцене». См. [[Features/Scenes]].

Copilot flow:

1. Сначала персонаж должен быть на сцене (блок «На сцене»). Selector текущих NPC из `GET /api/scenes/{scene}/participants` и ситуационный `prompt`
2. `POST /api/copilot/drafts` — три черновика и `copilot_request_id` (внутри API: топики → поиск → реплика); loading и ошибки API
3. Выбор черновика, правка в textarea
4. `POST /api/messages` с `{ body, character_id, scene_id, copilot_request_id, copilot_draft_index }` — отправка и трассировка выбранного результата

При смене сцены сохранённый ID и черновики сбрасываются. После успешной отправки повторное использование той же генерации в UI невозможно.

Игроки видят сцены, ленту и свой composer; управление сценами и панель copilot не рендерить.

Подробнее: [[Features/Copilot]], [[API/Copilot]].
