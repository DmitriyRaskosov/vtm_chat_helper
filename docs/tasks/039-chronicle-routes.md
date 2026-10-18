# 039 — Маршруты внутри хроники и CurrentChronicle из URL

- **Stage:** 3
- **Status:** done
- **Depends on:** 038
- **Risk:** high
- **Part:** 4
- **Разрешённые зависимости:** нет

## Goal

Коллекции текущей хроники открываются только под `/api/chronicles/{chronicle}/...`, а `CurrentChronicle` берёт эту хронику из URL и больше не подставляет первую по `id`.

## Context

- План §«Этап 3», задача 039. ADR-007 п. 5.
- Сейчас `App\Chronicle\CurrentChronicle::get()` делает `Chronicle::query()->orderBy('id')->first()` и иначе `NoChronicleException` (409, текст `There is no available chronicle.`).
- План перечисляет коллекции `messages`, `characters`, `game-sessions/active`. Ещё три вызова читают `CurrentChronicle` / `ActiveSceneResolver` и без того же префикса сломаются, когда fallback исчезнет: `POST /game-sessions` (`GameSessionController::store`), `POST /copilot/drafts` (`CopilotController` → `ActiveSceneResolver`), `POST /characters` (`CharacterCreationService`). Их перенести тем же коммитом задачи. Это не новый продукт, а условие «без fallback».
- Кто получает хронику. Напрямую инжектят `CurrentChronicle`: `CharacterSheetController`, `GameSessionController`, `CharacterCreationService`. `ChatController` и `CopilotController` сами `CurrentChronicle` не инжектят — они вызывают `ActiveSceneResolver`, а тот инжектит `CurrentChronicle`. Методы этих четырёх контроллеров не переписывать: после смены резолвера они увидят хронику URL.
- Плоскими остаются маршруты по модели: `/scenes/{scene}`, `/characters/{character}`, `/messages/{message}`, `/diary-entries/{diaryEntry}`, плюс вложенные в них `context`, `participants`, `disciplines`, `biography`, `traits`, `place`, `archive`, `restore`, `diary`, `ooc`, `restore`. Policy берёт хронику из модели (038).
- `GET /character-sheet/catalog` `CurrentChronicle` не использует (канон) — не переносить.
- `POST /api/invitations/{token}/accept` не переносить (037).
- `GET/POST /api/chronicles` остаются без параметра `{chronicle}` в пути списка и создания.
- В 038 (шаг 5) `CharacterSheetController::index` уже переведён с `isStoryteller()` на `isStorytellerIn(CurrentChronicle)`. Здесь — только добавить фильтр `chronicle_id` в запрос PC. Не менять `isStorytellerIn`-ветку.
- `CharacterSheetController::index` ищет PC так: `where user_id` + `character_type = player` без `chronicle_id`. При двух хрониках `value('id')` вернёт произвольного. Здесь добавить фильтр по id из `CurrentChronicle`.
- `ActiveSceneResolver::findInActiveSession`: если передан `scene_id`, фильтр хроники сейчас выключен (`$chronicleId = null`). Дыру «чужой scene_id» закрывает 040. В этой задаче резолвер без `scene_id` обязан звать новый `CurrentChronicle`.
- Группа `{chronicle}`: `->can('view', 'chronicle')` (`ChroniclePolicy::view`, 038) — не-участник получает 403. Открытый вопрос ADR «403 или 404» не закрывать и на 404 не переключать.
- Фронт ходит на старые пути до 042b. Сборку фронта не чинить под новые URL.
- После этой задачи фронт сломан до 042b: старые маршруты API исчезли, новые в фронте ещё не используются. Не деплоить, не чинить фронт здесь. Фронт починится в 042b.
- Под /api/chronicles/{chronicle}/... переезжают не только три 
  коллекции из плана (messages, characters, game-sessions/active), 
  но и POST /game-sessions, POST /copilot/drafts — они читают 
  CurrentChronicle, fallback исчезает.
- GET /character-sheet/catalog остаётся плоским (не привязан к хронике).
- Chat и Copilot не инжектят CurrentChronicle сами — его инжектит 
  ActiveSceneResolver. При переносе убедиться, что ActiveSceneResolver 
  получает chronicle из URL (route binding), а не из CurrentChronicle.

## Scope

- `app/Chronicle/CurrentChronicle.php`
- `app/Scene/ActiveSceneResolver.php` — не добавлять фильтр по `scene_id` (это 040); убедиться, что ветка без id вызывает `CurrentChronicle`
- `app/Http/Controllers/ChatController.php` — не менять методы, только если сигнатура конструктора мешает (не должна)
- `app/Http/Controllers/CopilotController.php` — то же
- `app/Http/Controllers/CharacterSheetController.php` — фильтр PC в `index` по хронике URL; методы листа по `{character}` не переносить
- `app/Http/Controllers/GameSessionController.php` — методы не переписывать
- `app/Character/CharacterCreationService.php` — не переписывать, хроника придёт из URL, потому что `POST /characters` внутри группы
- `routes/api.php`
- `tests/Feature/CurrentChronicleTest.php` и тесты, которые бьют в перенесённые URI (`tests/Feature`, `tests/Support/AccessMatrixFixtures.php`, `AccessMatrixTest.php`) — только новый путь, ожидания не ослаблять

**Out of scope:**
- фильтр `ActiveSceneResolver` при явном `scene_id` (040);
- `ScenePolicy::view` (044);
- замена путей во `frontend/src` (042b);
- удаление `users.role` (041).

## Steps

1. Переписать `CurrentChronicle`: хроника = route parameter `chronicle`, если это модель `Chronicle`. Иначе `NoChronicleException` с тем же текстом. Запроса `orderBy('id')->first()` больше нет.
2. Сгруппировать под `Route::prefix('/chronicles/{chronicle}')->scopeBindings()->can('view', 'chronicle')` внутри `auth:sanctum`:
   - `GET` и `POST /messages`;
   - `GET` и `POST /characters`;
   - `GET /game-sessions/active`;
   - `POST /game-sessions`;
   - `POST /copilot/drafts`.
   Имена ability и контроллеры те же. `scopeBindings()` оставить на группе, даже если дочерних параметров кроме `{chronicle}` нет.
3. Плоские маршруты из Context не двигать. `GET /character-sheet/catalog` не двигать.
4. `CharacterSheetController::index`: в запросе PC добавить `where('chronicle_id', CurrentChronicle::get()->id)`, не трогая ветку `isStorytellerIn` (она из 038).
5. Переписать `CurrentChronicleTest`: без параметра маршрута → 409 с прежним текстом; при двух хрониках запрос в `/api/chronicles/{вторая}/game-sessions/active` берёт вторую, не минимальный id.
6. Обновить URI в тестах и в матрице доступа. Страж «каждый маршрут api есть в матрице» зелёный.

## Definition of Done

- [ ] `rg "orderBy\('id'\)->first" app/Chronicle` → пусто
- [ ] `GET /api/messages`, `GET /api/characters`, `GET /api/game-sessions/active` без префикса хроники отсутствуют (`php artisan route:list`)
- [ ] плоские `GET /api/characters/{character}` и `GET /api/scenes/{scene}/context` на месте
- [ ] четыре контроллера из Context не содержат своей логики выбора хроники (её нет и сейчас, кроме чтения `CurrentChronicle`)
- [ ] тест двух хроник из шага 5 зелёный
- [ ] `sail pint --test` — чисто
- [ ] `sail php vendor/bin/phpstan analyse` — без новых ошибок; baseline не вырос. В отчёте `baseline: <до> → <после>`
- [ ] `sail artisan test` — зелёные. В отчёте `tests: <до> → <после>`
- [ ] `sail artisan migrate:fresh --seed` — проходит
- [ ] `cd frontend; npm run build` — без ошибок (пути в исходниках старые, сборка обязана проходить; ручной прогон UI до 042b не требуется)

## Report

Формат — `.cursor/rules/task-execution.mdc` §5. Дополнительно: список перенесённых URI; подтверждение, что Chat и Copilot идут через `ActiveSceneResolver`.
