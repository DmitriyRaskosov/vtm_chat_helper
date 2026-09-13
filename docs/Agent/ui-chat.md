# UI: чат

Route: `/chat`. Shell: `frontend/src/views/ChatView.vue`.

До первой правки открой не больше 6 файлов из таблицы. Не читай репозиторий целиком.

## Задача → файлы

| Задача | Файлы |
|--------|--------|
| Оболочка: poll, смена сцены, AppNav | `frontend/src/views/ChatView.vue` |
| Сессия, сцены, «На сцене» | `frontend/src/composables/useSceneSession.js`, `frontend/src/components/chat/SceneToolbar.vue` |
| Лента и poll load | `frontend/src/composables/useChatMessages.js`, `frontend/src/components/chat/ChatLog.vue` |
| Composer игрока | `frontend/src/components/chat/ChatComposer.vue` |
| Copilot ST | `frontend/src/components/chat/StorytellerCopilotPanel.vue` |
| Шапка | `frontend/src/components/layout/AppNav.vue` |

Контракты (не менять без задачи на API):

- `POST /api/copilot/drafts` — `character_id`, `prompt`, `scene_id`
- `POST /api/messages` игрок — `body`, `scene_id`
- `POST /api/messages` NPC — `body`, `character_id`, `scene_id`, `copilot_request_id`, `copilot_draft_index`

Поведение: poll 3 с; смена сцены чистит ленту и copilot drafts; composer только если сцена `active`.

## Не открывать при UI-only

- `docs/Meta/Structure.md`
- `app/Llm/NpcCopilotService.php`, `app/Context/**`
- `app/Lore/LoreSearcher.php`, `app/Retrieval/**`
- `routes/api.php`, миграции, Form Requests
- `frontend/src/views/WorldView.vue`, `CharacterSheetView.vue`
