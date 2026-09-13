# UI: мир и лор

Route: `/world` (только рассказчик). Shell: `frontend/src/views/WorldView.vue`.

До первой правки открой не больше 6 файлов из таблицы. Не читай репозиторий целиком.

State справочника и лора живёт в view (composables вызываются там). Не переносить `useWorldLore` внутрь вкладки: `v-if` табов уничтожит `characterOptions` / форму статьи.

## Задача → файлы

| Задача | Файлы |
|--------|--------|
| Оболочка: табы, error, loadBase | `frontend/src/views/WorldView.vue` |
| Справочник entities | `frontend/src/composables/useWorldEntities.js`, `frontend/src/components/world/WorldDirectoryTab.vue` |
| Политика фракций | `frontend/src/composables/useWorldPolitics.js`, `frontend/src/components/world/WorldPoliticsSection.vue` |
| Вкладка лора, grant/deny | `frontend/src/composables/useWorldLore.js`, `frontend/src/components/world/WorldLoreTab.vue` |
| Шапка | `frontend/src/components/layout/AppNav.vue` |

Контракты API:

- `GET/POST /api/world/entities`, `PUT /api/world/entities/{id}`, archive/restore
- `GET/POST /api/world/faction-relations`, `.../end`
- `POST|PUT /api/lore` — `title`, `canonical_text`, `kind`, `visibility` (всегда `public` из UI), `classification` (`0`–`5`), `situational`, `entity_ids`, `granted_character_ids`, `denied_character_ids`

Grant снимает deny и наоборот (`onExceptionToggle`).

## Не открывать при UI-only

- `docs/Meta/Structure.md`
- `app/World/**`, `app/Lore/**` (кроме явной регрессии API)
- `app/Llm/**`, `app/Context/**`, `LoreSearcher.php`
- `routes/api.php`, миграции, Form Requests
- `frontend/src/views/ChatView.vue`, `CharacterSheetView.vue`
