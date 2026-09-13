# UI: лист персонажа

Route: `/characters/:id`. Shell: `frontend/src/views/CharacterSheetView.vue`.

До первой правки открой shell + composable + одну секцию (обычно 3 файла). Не читай репозиторий целиком.

Гуль: `character_type === 'ghoul'` — compact (нет abilities / advantages / merits / humanity).

## Задача → файлы

| Задача | Файлы |
|--------|--------|
| Оболочка, compact, error/loading | `frontend/src/views/CharacterSheetView.vue` |
| API и state листа | `frontend/src/composables/useCharacterSheet.js` |
| Точки 1–N | `frontend/src/components/sheet/TraitDots.vue` |
| Blood / WP / health / XP | `frontend/src/components/sheet/SheetPlayBar.vue` |
| Name / Nature / шапка | `frontend/src/components/sheet/SheetHeaderSection.vue` |
| Attributes / Abilities | `frontend/src/components/sheet/SheetTraitSection.vue` |
| Disciplines / backgrounds / virtues | `frontend/src/components/sheet/SheetAdvantagesSection.vue` |
| Merits & flaws | `frontend/src/components/sheet/SheetMeritsSection.vue` |
| Humanity / Willpower | `frontend/src/components/sheet/SheetOtherSection.vue` |
| Биография | `frontend/src/components/sheet/SheetBiographySection.vue` |
| Место в мире + lore_clearance_levels | `frontend/src/components/sheet/SheetPlaceSection.vue` |
| Шапка сайта | `frontend/src/components/layout/AppNav.vue` |

Контракт места:

- `PUT /api/characters/{id}/place` — `sect_entity_id`, `clan_entity_id`, `haven_entity_id`, `lore_clearance_levels` (int[] `0`–`5`, уникальные)

Чекбоксы уровней и кнопки «0–N». Редактор места только у рассказчика.

## Не открывать при UI-only

- `docs/Meta/Structure.md`
- `app/Character/**` (кроме случая явной регрессии API)
- `app/Lore/**`, `app/Llm/**`, `app/Context/**`
- `routes/api.php`, миграции, Form Requests
- `frontend/src/views/ChatView.vue`, `WorldView.vue`
