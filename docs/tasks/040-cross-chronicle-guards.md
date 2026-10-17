# 040 — Объекты одной хроники не смешиваются

- **Stage:** 3
- **Status:** todo
- **Depends on:** 039
- **Risk:** med
- **Part:** 4
- **Разрешённые зависимости:** нет

## Goal

Участник сцены, сир, персонаж-автор сообщения и запись дневника принадлежат той же хронике, что и сцена, а явный `scene_id` не открывает сцену другой хроники.

## Context

- План §«Этап 3», задача 040: участники, сир, автор сообщения, дневник — всё в пределах одной хроники.
- Уже есть и не ослаблять:
  - `SceneParticipantService` бросает `MixedChronicleException` (409), если `character.chronicle_id` не равен хронике сессии сцены;
  - `WorldEntityService` при записи сира вызывает `assertSameChronicle` и не даёт сира из другой хроники; FK `(sire_character_id, chronicle_id)` уже в миграции;
  - `SceneContextService` так же проверяет локацию;
  - `CharacterPolicy::viewInScene` бросает `CharacterNotInChronicleException`, если персонаж из другой хроники, чем сцена. HTTP `POST /messages` с `character_id` доходит до сервиса только после этой Policy.
- Дыры:
  - `MessageService::resolveWorldAuthor` хронику персонажа не проверяет. Обход Policy (вызов сервиса из кода) запишет чужого автора. Проверка нужна в сервисе, `MixedChronicleException`.
  - `ActiveSceneResolver::findInActiveSession`: при переданном `sceneId` фильтр `chronicle_id` отключается (`$chronicleId = null`). После 039 `CurrentChronicle` — хроника URL, но чужой `scene_id` в query всё ещё находит сцену. Фильтр нужен и когда id передан: сцена активной сессии этой хроники, иначе прежние исключения резолвера (не 200 с чужой сценой).
  - `DiaryEntryRepository` пишет `chronicle_id` персонажа и не сравнивает его с хроникой сцены. Если NPC и сцена из разных хроник, запись не создавать: доменное исключение модуля Diary (`ConflictException`), как `MixedChronicleException`.
- Локацию сцены план в этой задаче не называет. Существующую проверку не трогать и новым тестом не дублировать, если он уже есть.
- Форму JSON и `ContextAssembler` не менять.

## Scope

- `app/Scene/ActiveSceneResolver.php` — фильтр хроники при явном `sceneId`
- `app/Messages/MessageService.php` — `resolveWorldAuthor`
- `app/Diary/DiaryEntryRepository.php` и вызывающий код записи L0/L1, если сравнение удобнее одним уровнем выше (`DiaryWriterService`) — одна проверка, не две расходящиеся
- `app/Diary/Exceptions/` — исключение конфликта хроники, если подходящего класса нет
- `tests/Feature/CrossChronicleGuardTest.php` (создать)
- существующие тесты сира и участников — только если ожидание уже описывает 409; не ослаблять

**Out of scope:**
- Policies и матрица чужого пользователя (038, 044);
- смена маршрутов (039);
- сид демо (043);
- проверка локации, если она уже зелёная.

## Steps

1. Найти текущие тесты `MixedChronicleException` / сира / участника. Что уже зелёное — не переписывать, перечислить в отчёте.
2. `ActiveSceneResolver`: и при `sceneId === null`, и при переданном id сессия сцены фильтруется по `CurrentChronicle->id()`. Чужой id → то же исключение, что и отсутствующая сцена (`NoAvailableSceneException` или `CopilotRequiresActiveSceneException` / `InactiveSceneForMessagesException` по ветке), не сцена другой хроники.
3. `MessageService::resolveWorldAuthor`: персонаж с другим `chronicle_id`, чем `scene.gameSession.chronicle_id`, → `MixedChronicleException`. Сообщение не вставлять.
4. Запись дневника: `character.chronicle_id` и хроника сцены совпадают, иначе исключение Diary и строка в `character_diary_entries` не появляется.
5. `CrossChronicleGuardTest` (две хроники, фабрики 034a, членства 035):
   - участник из чужой хроники → 409, строки `scene_participants` нет;
   - сир из чужой хроники → 409;
   - `POST .../messages` с персонажем чужой хроники → 409, сообщения нет;
   - `scene_id` чужой хроники на `GET /api/chronicles/{эта}/messages` не возвращает её сообщения;
   - запись дневника для NPC другой хроники на сцене этой — исключение, строки нет.

## Definition of Done

- [ ] пять проверок шага 5 зелёные
- [ ] прежние тесты сира и участников не удалены и не ослаблены
- [ ] `rg "chronicleId = null" app/Scene/ActiveSceneResolver.php` → пусто
- [ ] `sail pint --test` — чисто
- [ ] `sail php vendor/bin/phpstan analyse` — без новых ошибок; baseline не вырос. В отчёте `baseline: <до> → <после>`
- [ ] `sail artisan test` — зелёные. В отчёте `tests: <до> → <после>`
- [ ] `sail artisan migrate:fresh --seed` — проходит

## Report

Формат — `.cursor/rules/task-execution.mdc` §5. Дополнительно: какие из четырёх границ уже были в коде, какие добавлены; класс исключения дневника.
