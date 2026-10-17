# 034b — Фронт: превью дневника, вход, реэкспорты меток

- **Stage:** 2
- **Status:** todo
- **Depends on:** 022, 034
- **Risk:** low
- **Part:** 3
- **Разрешённые зависимости:** нет

## Goal

Ответ превью дневника идёт через `DiaryPreviewResource` без смены полей, форма входа и регистрации живёт в composables, а метки типа персонажа импортируются только из `labels.js`.

## Context

- Ревью Part 3. Задача 022 явно исключила `POST /characters/{id}/diary/preview`: `DiaryEntryController::preview` возвращает массив `App\Context\DiaryPreviewService::preview()` как есть. Форма не меняется.
- Ключи корня: `character_id`, `scene_id`, `recent_first_message_id`, `prompt`, `budget`, `tokens_estimate`, `truncated`, `stats`, `selected`, `rendered`.
- Ключи элемента `selected`: `id`, `level`, `scene_id`, `from_message_id`, `to_message_id`, `created_at` (ISO 8601), `label`, `excerpt` (200 символов), `importance`, `emotion`.
- Задача 034 вынесла API экранов дневника, списка персонажей и Copilot, но `LoginView.vue` и `RegisterView.vue` по-прежнему держат состояние формы, разбор `errors` и редирект в `<script setup>`. Сессия уже в `useAuth()` (`auth.js`).
- Реэкспорты: `useSceneSession.js` делает `export { characterTypeLabel }`; `useCharacterSheet.js` делает `export { characterTypeLabel as typeLabel }`. Потребители: `SceneToolbar.vue`, `SheetHeaderSection.vue`. `CharacterListView.vue` уже импортирует из `labels.js`.
- `.cursor/rules/frontend.mdc` сейчас требует эти реэкспорты «для старых импортов».
- Если файл `app/Diary/DiaryPreviewService.php` ещё есть — контроллер его не вызывает. Не трогать.

## Scope

- `app/Http/Resources/DiaryPreviewResource.php` (создать)
- `app/Http/Controllers/DiaryEntryController.php` — только метод `preview`
- `tests/Feature/DiaryJsonShapeTest.php` — кейс превью (добавить, если его нет)
- `frontend/src/composables/useLogin.js`, `frontend/src/composables/useRegister.js` (создать)
- `frontend/src/views/LoginView.vue`, `frontend/src/views/RegisterView.vue`
- `frontend/src/composables/useSceneSession.js`, `frontend/src/composables/useCharacterSheet.js` — снять реэкспорт
- `frontend/src/components/chat/SceneToolbar.vue`, `frontend/src/components/sheet/SheetHeaderSection.vue` — импорт из `frontend/src/utils/labels.js`
- `.cursor/rules/frontend.mdc` — убрать требование реэкспорта меток

**Out of scope:**
- поля и тексты превью, `DiaryPreviewService`, `ContextAssembler`;
- `auth.js` (токен, `isStoryteller`, axios) — задачи 032 и 041;
- разметка и `styles.css`;
- удаление дубля `app/Diary/DiaryPreviewService.php`, если он есть.

## Steps

1. Тест формы `POST /characters/{id}/diary/preview` до правок: те же ключи корня и элемента `selected`, что в Context. Тест зелёный на текущем массиве сервиса.
2. `DiaryPreviewResource` отдаёт этот массив без переименования ключей. `DiaryEntryController::preview` возвращает Resource. Повтор теста без смены ожиданий.
3. `useLogin`: поля `login`/`password`, ошибки, `submit()` вызывает `useAuth().login` и при успехе переходит на `/chat`. `LoginView` только шаблон и composable.
4. `useRegister`: поля формы, ошибки, `submit()` вызывает `useAuth().register` и при успехе переходит на `/chat`. Текст «Первый зарегистрированный становится рассказчиком…» не менять (его снимет 041).
5. Удалить реэкспорты `characterTypeLabel` / `typeLabel`. `SceneToolbar.vue` и `SheetHeaderSection.vue` импортируют `characterTypeLabel` из `utils/labels.js` (`typeLabel` как имя импорта не оставлять). Правило во `frontend.mdc` поправить на прямой импорт.

## Definition of Done

- [ ] тест превью зелёный до и после без смены ожидаемых ключей
- [ ] `rg "export \{ characterTypeLabel" frontend/src` → пусто
- [ ] `rg "from '../../composables/useSceneSession'|from '../../composables/useCharacterSheet'" frontend/src/components` — нет импорта метки типа (импорт функций сессии/листа может остаться)
- [ ] `rg "apiErrorMessage|useRouter" frontend/src/views/LoginView.vue frontend/src/views/RegisterView.vue` → пусто
- [ ] `cd frontend; npm run build` — без ошибок
- [ ] ручная проверка: вход с неверным паролем показывает ошибку; регистрация создаёт сессию и открывает чат; превью дневника в панели Copilot открывается
- [ ] `sail pint --test` — чисто
- [ ] `sail php vendor/bin/phpstan analyse` — без новых ошибок; baseline не вырос (снимок на 18.10.2026: 9 вхождений `message:` в `phpstan-baseline.neon`; перед правками пересчитать в контейнере). В отчёте `baseline: <до> → <после>`
- [ ] `sail artisan test` — зелёные. В отчёте `tests: <до> → <после>`
- [ ] `sail artisan migrate:fresh --seed` — проходит (схема не меняется, прогон как дымовой)

## Report

Формат — `.cursor/rules/task-execution.mdc` §5. Дополнительно: ключи JSON превью (совпали / нет); куда переехали `submit` логина и регистрации.
