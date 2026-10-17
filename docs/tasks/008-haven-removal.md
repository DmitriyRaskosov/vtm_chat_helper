# 008 — Удаление гавани персонажа

- **Stage:** 1
- **Status:** done
- **Depends on:** 004
- **Risk:** med
- **Разрешённые зависимости:** нет

## Goal

Гавани персонажа нет ни в API, ни во фронте; секта берётся только из `characters.sect_id`; фронт не вызывает ни одного маршрута `/world/*`.

## Context

- ADR-002 п. 6.
- План, «Факты»: `activeSect` в `CharacterSheetReader` — мёртвый вызов (`$sect` не используется, секта из `$character->sect`). `activeHaven` тянет `CharacterPlaceService` (ветка `haven_entity_id`, зависимость от `WorldRelationService`), `UpdateCharacterPlaceRequest`, `WorldRelationService::setHaven/activeHaven/setSect/activeSect`, авто-синхронизацию `member_of` в `WorldEntityService` (~строки 505–540); на фронте — блок гавани в `SheetPlaceSection.vue` и загрузку `/world/entities` в `useCharacterSheet.js`.

## Scope

- `app/Character/CharacterSheetReader.php`
- `app/Character/CharacterPlaceService.php`
- `app/Http/Requests/UpdateCharacterPlaceRequest.php`
- `app/World/WorldRelationService.php` — только удаление `setHaven`, `activeHaven`, `setSect`, `activeSect`
- `app/World/WorldEntityService.php` — только удаление sect-sync (`member_of`)
- `frontend/src/components/sheet/SheetPlaceSection.vue`
- `frontend/src/composables/useCharacterSheet.js`
- `tests/Feature/CharacterTest.php`
- `docs/Scope.md` — только раздел Characters

**Out of scope:** `WorldRelationController`, `WorldEntityController`, маршруты `/world/*` (задачи 009, 010); остальные методы `WorldRelationService`.

## Steps

1. Характеризационный тест `PUT /characters/{id}/place` (клан и секта сохраняются, ответ листа содержит их) — зелёный до изменений.
2. Удалить вызовы `activeSect`/`activeHaven` и поля `haven_*` из `CharacterSheetReader`.
3. `CharacterPlaceService`: убрать ветку `haven_entity_id` и зависимость от `WorldRelationService`, если она больше не нужна.
4. `UpdateCharacterPlaceRequest`: убрать правила `haven_*`.
5. `WorldRelationService`: удалить 4 метода. `WorldEntityService`: удалить sect-sync.
6. Фронт: убрать блок гавани и загрузку `/world/entities`.
7. Дописать в тест: ответ листа не содержит ключей `haven*`; передача `haven_entity_id` в PUT игнорируется или отклоняется (как в текущей валидации для неизвестных полей).

## Definition of Done

- [ ] `rg -i "haven" app frontend/src` → пусто
- [ ] `rg "/world/" frontend/src` → пусто
- [ ] `rg "activeSect|setSect|member_of" app` → пусто
- [ ] тест `PUT /place` зелёный
- [ ] `sail artisan test` — зелёные; `sail pint --test` — чисто; phpstan — без новых ошибок
- [ ] `cd frontend; npm run build` — без ошибок; ручная проверка: секция «Место» листа сохраняет клан и секту
- [ ] `docs/Scope.md`: из Characters убрано «гавань»

## Report

Формат — `.cursor/rules/task-execution.mdc` §5.
