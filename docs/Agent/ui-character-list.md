# UI: список персонажей

Route: `/characters`. Shell: `frontend/src/views/CharacterListView.vue`.

До первой правки открой не больше 4 файлов из таблицы. Не читай репозиторий целиком.

## Задача → файлы

| Задача | Файлы |
|--------|--------|
| Список, создание PC/NPC/гуля, скрыть/вернуть | `frontend/src/views/CharacterListView.vue` |
| Шапка | `frontend/src/components/layout/AppNav.vue` |
| HTTP-клиент | `frontend/src/auth.js` (`api`, `baseURL: /api`) |
| Стили списка | `frontend/src/styles.css` (классы `character-*`) |

Маршрут листа: `/characters/:id` → `docs/Agent/ui-character-sheet.md`.

Рассказчик видит форму создания и блок скрытых. Игрок — свой PC и гулей. Copilot на этом экране нет.

## Не открывать при UI-only

- `docs/Meta/Structure.md`
- `app/Character/**`, `CharacterSheetController.php`
- `routes/api.php`, миграции, Form Requests
- `frontend/src/views/ChatView.vue`, `WorldView.vue`
- `app/Llm/**`, `app/Context/**`, `app/Lore/**`
