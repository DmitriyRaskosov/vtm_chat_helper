# 042b — Маршруты фронта с id хроники

- **Stage:** 3
- **Status:** todo
- **Depends on:** 039, 042a
- **Risk:** high (много экранов)
- **Part:** 4
- **Разрешённые зависимости:** нет

## Goal

Чат, список персонажей, лист и дневник открываются по маршруту с id выбранной хроники и ходят в коллекции API только внутри `/api/chronicles/{id}/...`.

## Context

- План §«Этап 3», задача 042, вторая часть. Бэкенд коллекций с 039:
  - `GET/POST /api/chronicles/{chronicle}/messages`;
  - `GET/POST /api/chronicles/{chronicle}/characters`;
  - `GET /api/chronicles/{chronicle}/game-sessions/active`;
  - `POST /api/chronicles/{chronicle}/game-sessions`;
  - `POST /api/chronicles/{chronicle}/copilot/drafts`.
- Плоские пути не менять: `/api/characters/{id}` и вложенные лист/дневник, `/api/scenes/{scene}/...`, `/api/messages/{id}`, `/api/diary-entries/{id}`, `/api/character-sheet/catalog`, `POST /api/game-sessions/{id}/scenes`.
- `useCurrentChronicle` (042a) даёт `currentId`. Если id в URL нет или его нет среди хроник пользователя — на `/chronicles`, запрос коллекции не отправлять.
- Сейчас коллекции вызывают: `useSceneSession.js`, `useChatMessages.js`, `useCharacterList.js`, `useCopilotPanel.js`. Лист (`useCharacterSheet.js`) и дневник (`useDiary.js`) бьют в плоские пути — префикс им не нужен, кроме ссылок роутера.
- `meta.storyteller` пока смотрит глобальный `isStoryteller` из 041. Не переопределять (042d).

## Scope

- `frontend/src/router.js`
- `frontend/src/composables/useSceneSession.js`
- `frontend/src/composables/useChatMessages.js`
- `frontend/src/composables/useCharacterList.js`
- `frontend/src/composables/useCopilotPanel.js`
- `frontend/src/views/ChatView.vue`, `CharacterListView.vue`, `CharacterSheetView.vue`, `DiaryView.vue` — ссылки `RouterLink` / `router.push` на новые имена
- `frontend/src/components/layout/AppNav.vue` — ссылки чата и персонажей ведут в текущую хронику
- `frontend/src/composables/useCurrentChronicle.js` — чтение id из маршрута, если 042a хранил его только в `localStorage`

**Out of scope:**
- экран принятия приглашения (042c);
- формула роли (042d);
- плоские API-пути листа, сцены, сообщения по id, дневника;
- бэкенд.

## Steps

1. Маршруты (все `meta.auth: true`, дневник ещё и `meta.storyteller: true`):
   - `/c/:chronicleId/chat` имя `chat`;
   - `/c/:chronicleId/characters` имя `characters`;
   - `/c/:chronicleId/characters/:id` имя `character`;
   - `/c/:chronicleId/characters/:id/diary` имя `character-diary`.
   Старые `/chat` и `/characters` без id: редирект на `/c/${currentId}/...`, если хроника выбрана, иначе на `chronicles`. `/` → тот же редирект, не на голый `/chat`.
2. В composables из Scope подставить id из маршрута в пять коллекций 039. Без id метод выходит с ошибкой «Хроника не выбрана», запрос не шлёт.
3. Навигация: «Чат» и «Персонажи» в `AppNav` и переходы после создания персонажа / открытия дневника используют `chronicleId` текущего маршрута.
4. Ручной прогон под рассказчиком демо (после 043; если 043 ещё не сделана — под хроникой, созданной с экрана 042a): чат грузит сессию и сообщения; список персонажей открывается; лист по id открывается; отправка сообщения и черновик Copilot бьют в URL с id хроники (вкладка сети).

## Definition of Done

- [ ] `rg "api\.(get|post)\('/(messages|characters|game-sessions|copilot/drafts)" frontend/src` → пусто (эти пути только с префиксом `/chronicles/${…}/`)
- [ ] `rg "path: '/chat'|path: '/characters'" frontend/src/router.js` → пусто
- [ ] `cd frontend; npm run build` — без ошибок
- [ ] ручная проверка шага 4 и редирект без выбранной хроники на `/chronicles`
- [ ] PHP-проверки (pint, phpstan, artisan test, migrate:fresh) не требуются, если diff только во `frontend/`

## Report

Формат — `.cursor/rules/task-execution.mdc` §5. Дополнительно: таблица «старый вызов → новый путь»; что проверено в браузере.
