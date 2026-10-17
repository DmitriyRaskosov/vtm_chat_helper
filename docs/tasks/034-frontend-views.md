# 034 — Фронт: логика экранов в composables

- **Stage:** 2
- **Part:** 3
- **Status:** done
- **Depends on:** 033
- **Risk:** med (поведение экранов дневника и Copilot)
- **Разрешённые зависимости:** нет

## Goal

`DiaryView`, `CharacterListView` и `StorytellerCopilotPanel` не вызывают API напрямую — логика живёт в composables, а `useCharacterSheet` не привязан к жизненному циклу и роутеру изнутри.

## Context

- Аудит §2.5:
  - `DiaryView.vue` (~260 строк): `api.get('/characters/{id}')`, `/diary`, `/diary-entries/{id}` GET/PATCH/DELETE, `/regenerate`;
  - `CharacterListView.vue` (~146): `GET/POST /characters`, `archive`, `restore`;
  - `StorytellerCopilotPanel.vue` (~250): `POST /copilot/drafts`, `POST /messages`, `POST /characters/{id}/diary/preview`;
  - `useCharacterSheet` регистрирует `onMounted` и `watch(route)` внутри себя; `useSceneSession`, `useChatMessages` так не делают.
- Защита от гонок и очистка таймеров в существующих composables — образец.
- `.cursor/rules/frontend.mdc` `TODO(034)`.

## Scope

- `frontend/src/composables/useDiary.js`, `useCharacterList.js`, `useCopilotPanel.js` (создать)
- `frontend/src/composables/useCharacterSheet.js`
- `frontend/src/views/{DiaryView,CharacterListView,CharacterSheetView}.vue`
- `frontend/src/components/chat/StorytellerCopilotPanel.vue`
- `.cursor/rules/frontend.mdc` — закрыть `TODO(034)`: «view и компоненты не вызывают `api` напрямую»

**Out of scope:** разметка и стили (кроме привязки к новым composables); пропсы `SceneToolbar` (~20 пропсов — отдельная задача при необходимости); backend.

## Steps

1. Для каждого экрана — список действий пользователя и вызовов API (в отчёт) до правок.
2. Вынести состояние и вызовы в composables; view получает реактивное состояние и функции. Тексты ошибок — через `apiErrorMessage` (033).
3. `useCharacterSheet`: принимает `characterId` (ref/getter) и возвращает `load()`; `onMounted`/`watch(route)` переезжают в `CharacterSheetView.vue`.
4. Закрыть `TODO(034)`.

## Definition of Done

- [ ] `rg "api\.(get|post|patch|put|delete)" frontend/src/views frontend/src/components` → пусто
- [ ] `rg "onMounted|useRoute" frontend/src/composables/useCharacterSheet.js` → пусто
- [ ] `cd frontend; npm run build` — без ошибок
- [ ] ручная проверка по списку шага 1: дневник (список, открыть, правка, удаление, пересоздание), список персонажей (создание, архив, восстановление), панель Copilot (черновики, отправка, превью дневника), лист персонажа (смена id в URL перезагружает данные)
- [ ] `git diff --stat` — только файлы из Scope

## Report

Формат — `.cursor/rules/task-execution.mdc` §5. Дополнительно: список шага 1 с отметками ручной проверки.
