# 033 — Фронт: общие метки, хелпер ошибок, таймер «сохранено»

- **Stage:** 2
- **Part:** 3
- **Status:** todo
- **Depends on:** 032
- **Risk:** low
- **Разрешённые зависимости:** нет

## Goal

Метки «игрок/NPC», извлечение текста ошибки из ответа API и таймер «сохранено» реализованы по одному разу и переиспользуются.

## Context

- Аудит §2.2 (фронт):
  - словарь меток «игрок/NPC» — трижды: `composables/useSceneSession.js` (~стр. 12), `composables/useCharacterSheet.js` (~стр. 5), `views/CharacterListView.vue` (~стр. 87) — найти точные места `rg`;
  - `error.response?.data?.message ?? '…'` — около 31 вхождения в 7 файлах (`useSceneSession.js` 10, `useCharacterSheet.js` 6, `DiaryView.vue` 6, `CharacterListView.vue` 4, `StorytellerCopilotPanel.vue` 3, `LoginView.vue` 1, `RegisterView.vue` 1);
  - таймер «сохранено» — `useSceneSession.js:~100` (`participantFlashTimer`), `useCharacterSheet.js:~122` (`flashTimers[key]`).
- Ответы ошибок backend после 018/024a: `{ message }`, для 422 — плюс `errors`.
- Из 032: `isStoryteller` вынесен в `auth.js`, но остались прямые обращения в `useSceneSession.js:78`, `useSceneSession.js:220`, `CharacterListView.vue:87` (3 вхождения), `ChatView.vue:207` (truthy-проверка). В 033 — заменить на импорт `isStoryteller` из `auth.js`.
- `.cursor/rules/frontend.mdc` `TODO(033)`.

## Scope

- `frontend/src/utils/labels.js`, `frontend/src/utils/apiError.js` (создать)
- `frontend/src/composables/useFlash.js` (создать)
- `frontend/src/composables/{useSceneSession,useCharacterSheet}.js`
- `frontend/src/composables/useSceneSession.js` — `is_storyteller` → `isStoryteller`
- `frontend/src/views/{CharacterListView,DiaryView,LoginView,RegisterView}.vue`
- `frontend/src/views/CharacterListView.vue` — `is_storyteller` → `isStoryteller` (3 места)
- `frontend/src/views/ChatView.vue` — `is_storyteller` → `isStoryteller` (в `poll`:207, truthy → проверка на boolean; убедись, что поведение не изменилось)
- `frontend/src/components/chat/StorytellerCopilotPanel.vue`
- `.cursor/rules/frontend.mdc` — закрыть `TODO(033)`

**Out of scope:** перенос логики view в composables (034); тексты сообщений по умолчанию (сохранить те же fallback-строки); стили.

## Steps

1. `labels.js`: `characterTypeLabel(type)` и словарь — значения взять из существующих копий; если копии расходятся — остановиться и показать различия.
2. `apiError.js`: `apiErrorMessage(error, fallback)` — `error.response?.data?.message ?? fallback`. Заменить все вхождения, сохранив fallback каждого места.
3. `useFlash(ms = 2000)`: `flash(key)`, реактивное состояние, очистка таймеров в `onScopeDispose`. Заменить две реализации, длительности — как были.
4. Закрыть `TODO(033)`.

## Definition of Done

- [ ] `rg 'is_storyteller' frontend/src` — только в `auth.js` (или где экспортируется `isStoryteller`).
- [ ] `rg "response\?\.data\?\.message" frontend/src` → только `utils/apiError.js`
- [ ] `rg "setTimeout" frontend/src/composables frontend/src/views` → только `useFlash.js` (и несвязанные таймеры — перечислить)
- [ ] словарь меток объявлен один раз
- [ ] `cd frontend; npm run build` — без ошибок; ручная проверка: ошибка логина показывается, «сохранено» на листе персонажа исчезает через прежнее время
- [ ] `git diff --stat` — только файлы из Scope

## Report

Формат — `.cursor/rules/task-execution.mdc` §5.
