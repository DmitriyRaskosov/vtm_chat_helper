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
| Вкладка лора, grant/deny, экстрактор | `frontend/src/composables/useWorldLore.js`, `frontend/src/components/world/WorldLoreTab.vue` |
| Шапка | `frontend/src/components/layout/AppNav.vue` |

Форма справочника: тип (`faction` / `clan` / `coterie` / `circle` / `other` / `location` / `item` / `concept`). UI для `faction` — «Секта / фракция». У фракции — select «Родительская секта / фракция» (`parent_faction_id`). У клана/котерии/круга — select «Секта / фракция» (`sect_faction_id`). Список «Ещё имена» — aka.

Вкладка **Разбор** (`?tab=inbox`): inbox всех прогонов (`GET /api/extract/inbox`) — `needs_review` и `failed` для **лора, био и сцен**. Список с подписью источника; у сцен — диапазон id. Открыть run → PATCH mention + accept/discard (граф / память / события). Reparse — только для сцен (`POST /api/extract/{run}/reparse`). На вкладках **Лор** и **Био** — только кнопка «Разобрать»; после POST переход сюда (`?tab=inbox&run={id}`). Файлы: `WorldExtractionInboxTab.vue`, `useExtractionInbox.js`.

Контракты API:

- `GET/POST /api/world/entities`, `PUT /api/world/entities/{id}`, archive/restore — GET отдаёт `aliases` (aka)
- `GET/POST /api/world/faction-relations`, `.../end`
- `POST|PUT /api/lore` — `title`, `canonical_text`, `kind`, `visibility` (всегда `public` из UI), `classification` (`0`–`5`), `situational`, `entity_ids`, `granted_character_ids`, `denied_character_ids`
- `GET /api/extract/status`, `POST /api/extract`, `PATCH /api/extract/{run}/candidates/{index}`, `POST /api/extract/{run}/candidates/{index}/accept|discard` — см. [[API/Extract]]

На вкладке **Лор**: счётчик символов и «разбор за раз ≤ N» (лимиты из status API); для статьи &gt; 10k кнопка «Разобрать (A–B из C)»; после POST — переход на «Разбор». На вкладке **Разбор**: pending mention — имя, тип (directory kinds), optional секта для clan/coterie/circle; aka, alias_of. **Принять** = PATCH draft + accept одной кнопкой; inline errors у accept/discard. Невалидные relations — disabled accept + `discard_reason`. Matcher дедуплирует mentions и синтезирует missing endpoint из relations. Завершённый run — «Разбор завершён», не в списке inbox.

Grant снимает deny и наоборот (`onExceptionToggle`).

## Не открывать при UI-only

- `docs/Meta/Structure.md`
- `app/World/**`, `app/Lore/**` (кроме явной регрессии API)
- `app/Llm/**`, `app/Context/**`, `LoreSearcher.php`
- `routes/api.php`, миграции, Form Requests
- `frontend/src/views/ChatView.vue`, `CharacterSheetView.vue`
