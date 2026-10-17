# 006 — Фронтенд: сломанные ссылки и мёртвые компоненты

- **Stage:** 1
- **Status:** done
- **Depends on:** —
- **Risk:** low
- **Разрешённые зависимости:** нет

## Goal

На листе персонажа нет необъявленных компонентов и переменных, мёртвые компоненты листа и их стили удалены.

## Context

- Аудит §2.1.6: `SheetAdvantagesSection.vue` использует `<TraitDots>` без импорта (строки 7–11); `CharacterSheetView.vue:18` ссылается на необъявленную `compact`; `SheetPlayBar.vue` импортирует несуществующий `healthDamageKinds`.
- Аудит §2.5: мёртвые `SheetMeritsSection`, `SheetPlayBar`, `SheetOtherSection`, `SheetTraitSection`; их стили в `frontend/src/styles.css`.
- `.cursor/rules/frontend.mdc`.

## Scope

- `frontend/src/components/sheet/SheetAdvantagesSection.vue`
- `frontend/src/views/CharacterSheetView.vue`
- `frontend/src/components/sheet/SheetMeritsSection.vue` (удалить)
- `frontend/src/components/sheet/SheetPlayBar.vue` (удалить)
- `frontend/src/components/sheet/SheetOtherSection.vue` (удалить)
- `frontend/src/components/sheet/SheetTraitSection.vue` (удалить; не путать с `SheetTraitsSection.vue` — он живой)
- `frontend/src/styles.css` — только удаление классов, используемых исключительно удаляемыми компонентами

**Out of scope:**
- остальной `styles.css`, рефакторинг composables, backend.

## Steps

1. Перед удалением: `rg "SheetMeritsSection|SheetPlayBar|SheetOtherSection|SheetTraitSection\b" frontend/src` — убедиться, что импортов нет (кроме самих файлов). Если есть — остановиться.
2. `SheetAdvantagesSection.vue`: добавить `import TraitDots from './TraitDots.vue'`.
3. `CharacterSheetView.vue:18`: выяснить, что должно было означать `compact` (по контексту шаблона), заменить корректным выражением или убрать. Если смысл неясен — спросить.
4. Удалить 4 мёртвых компонента.
5. Для каждого CSS-класса из удалённых компонентов: `rg "<класс>" frontend/src` — удалить из `styles.css` только классы без других использований.

## Definition of Done

- [ ] `cd frontend; npm run build` — без ошибок и предупреждений
- [ ] `rg "SheetMeritsSection|SheetPlayBar|SheetOtherSection|SheetTraitSection\b|healthDamageKinds" frontend/src` → пусто
- [ ] `rg "\bcompact\b" frontend/src/views/CharacterSheetView.vue` → пусто (или только объявленная переменная — пояснить в отчёте)
- [ ] ручная проверка: лист персонажа открывается, блок преимуществ показывает точки, консоль браузера без ошибок Vue
- [ ] `git diff --stat` — только файлы из Scope

## Report

Формат — `.cursor/rules/task-execution.mdc` §5. Дополнительно:
- чем заменён `compact` и почему;
- список удалённых CSS-классов.
