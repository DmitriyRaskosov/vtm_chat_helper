# 026 — Лист персонажа: каталог и транзакции в сервисах Character

- **Stage:** 2
- **Part:** 2
- **Status:** todo
- **Depends on:** 025b
- **Risk:** med
- **Разрешённые зависимости:** нет

## Goal

`CharacterSheetController` не содержит транзакций и запросов к канону: каталог, создание, дисциплины, место и архивация выполняются сервисами модуля Character.

## Context

- После этапа 1 World и гавани в листе нет, задача меньше исходной.
- В контроллере сейчас:
  - `catalog()` — 4 прямых запроса к `canon_disciplines`, `canon_clans` (кланы и bloodlines), `canon_sects`;
  - `store()` — `DB::transaction` + `WorldEntityService::create(..., typed: [...])`;
  - `updateDisciplines()` — `DB::transaction` + цикл `set/clearCharacterDiscipline`;
  - `updatePlace()` — `DB::transaction` вокруг `CharacterPlaceService::apply`;
  - `hideCharacter()`/`restoreCharacter()` — `WorldEntityService::archive/restore` + `SceneParticipantService::leaveMutableScenes`.
- `listItem`/`archivedList` — дубль сериализации (уйдёт в 020, здесь не трогать).
- Baseline: `phpDoc.parseError` в `CharacterSheetController`, 5 записей `Character*Service` (`debt: 026`).
- Канон — только чтение.

## Scope

- `app/Character/CharacterCatalog.php` (создать) — чтение канона для листа
- `app/Character/CharacterCreationService.php` (создать) — или метод существующего сервиса
- `app/Character/CharacterArchiveService.php` (создать)
- `app/Character/DisciplineService.php` — метод `sync(Character, array $rows)` с транзакцией
- `app/Character/CharacterPlaceService.php` — транзакция внутри `apply`
- `app/Character/{CharacterBiographyService,CharacterIdentityService,CharacterTraitService}.php` — только записи baseline
- `app/Http/Controllers/CharacterSheetController.php`
- `phpstan-baseline.neon` — удаление закрытых записей
- `tests/Feature/CharacterTest.php`, `CharacterTraitTest.php`

**Out of scope:** форма JSON (020), `CharacterAccess` (030a), правило видимости `index` (уже сделано, перенос в Policy — 030a), `CharacterSheetReader::aggregate`.

## Steps

1. Характеризация: `GET /character-sheet/catalog` (форма и порядок), `PUT /disciplines` с уровнем 0 и ненулевым, `archive`/`restore` (персонаж выходит из открытых сцен, остаётся в закрытой).
2. Перенести каталог в `CharacterCatalog` (возвращает DTO или массив той же формы — контроллер отдаёт его без изменений).
3. Транзакции `store`, `updateDisciplines`, `updatePlace` — в сервисы. Контроллер не импортирует `DB`.
4. `hideCharacter`/`restoreCharacter` → `CharacterArchiveService::archive/restore` (одна транзакция на архивацию сущности, выход из сцен — после, как сейчас).
5. Закрыть записи baseline `debt: 026`.

## Definition of Done

- [ ] `rg "DB::|Canon[A-Za-z]+::query|WorldEntity::query" app/Http/Controllers/CharacterSheetController.php` → пусто
- [ ] тесты шага 1 зелёные, форма ответов не изменилась
- [ ] записи baseline `debt: 026` удалены
- [ ] ручная проверка: создание NPC, правка дисциплин, архивация в списке персонажей
- [ ] `sail artisan test` — зелёные; `sail pint --test` — чисто; phpstan — OK

## Report

Формат — `.cursor/rules/task-execution.mdc` §5.
