# 018 — Доменные исключения сервисов и единый маппинг в HTTP

- **Stage:** 2
- **Part:** 1
- **Status:** done
- **Depends on:** 019
- **Risk:** high (коды ответов; `MixedChronicleException` 422 → 409 меняет API)
- **Разрешённые зависимости:** нет

## Goal

Сервисы модулей World, Scene, Character и Chronicle бросают только доменные исключения, их перевод в HTTP-коды сделан в одном месте — `bootstrap/app.php`, а `MixedChronicleException` везде даёт 409.

## Context

- ADR-005. Решение пользователя: `MixedChronicleException` → **409** (конфликт состояния).
- Факт: `MixedChronicleException extends InvalidArgumentException`, поэтому `catch (InvalidArgumentException)` срабатывает первым: `CharacterSheetController::store/update/updatePlace` и `SceneParticipantController::store` отдают **422**; `SceneContextController::update` ловит его раньше и отдаёт 409. Baseline: `catch.alreadyCaught` ×3.
- `SceneContextService::assertMutable` бросает `ConflictHttpException('Сцена закрыта или локация уже изменена')`; его ловит `SceneParticipantService::leaveMutableScenes` (`catch (ConflictHttpException|InvalidArgumentException)`) — путь архивации персонажа.
- `InvalidArgumentException` из сервисов (~20 мест): `WorldEntityService`, `CharacterIdentityService`, `CharacterBiographyService`, `DisciplineService`, `SceneParticipantService`, `SceneContextService`. Контроллеры переводят их в 422 через `abort(422, $e->getMessage())`.
- `CharacterBiographyService` бросает `InvalidArgumentException('Biography is unchanged.')`, контроллер глушит его по тексту.
- `NoChronicleException` из 019 отдаёт 409 через собственный `render()`.

## Scope

- `app/Exceptions/DomainException.php`, `ConflictException.php`, `InvalidInputException.php`, `NotFoundException.php` (создать; абстрактные)
- `bootstrap/app.php` — только `withExceptions()`
- `app/World/MixedChronicleException.php`, `app/World/Exceptions/*` (создать по необходимости)
- `app/Scene/SceneContextService.php`, `app/Scene/SceneParticipantService.php`, `app/Scene/Exceptions/*`
- `app/Character/{CharacterIdentityService,CharacterBiographyService,DisciplineService}.php`, `app/Character/Exceptions/*`, `app/Character/BiographyVersionImmutableException.php`
- `app/World/WorldEntityService.php`
- `app/Chronicle/Exceptions/NoChronicleException.php`
- `app/Http/Controllers/{CharacterSheet,SceneContext,SceneParticipant}Controller.php` — только удаление `try/catch` + `abort` вокруг вызовов сервисов
- `tests/Feature/*.php`, `tests/Feature/DomainExceptionMappingTest.php` (создать)

**Out of scope:**
- `abort()` с бизнес-проверками внутри Chat, Copilot, Scene контроллеров — уходят в 024, 025b, 026a;
- `CharacterAccess`, `AccessDeniedHttpException` в `MessageEditService`, middleware `storyteller` — авторизация, задачи 030a/b;
- исключения `Llm` и ответы 502/503 Copilot — задача 024a;
- `RuntimeException` в `Diary` (глушатся внутри) — задача 028;
- `ContextAssembler` (`InvalidArgumentException` в строках ~109, ~201 остаются);
- `DiaryEntryController` — задача 027.

## Steps

1. **Характеризация до правок.** Таблица «точка → исключение → код → текст» для всех `catch` в Scope-контроллерах и `leaveMutableScenes`. Тест на каждую строку таблицы, включая **текущие 422** для `MixedChronicleException` в `CharacterSheetController::store`, `update`, `updatePlace`, `SceneParticipantController::store`. Тест «архивация персонажа, состоящего в закрытой сцене, → 200».
2. Базовые классы: `abstract class DomainException extends \RuntimeException`; `ConflictException` (409), `InvalidInputException` (422), `NotFoundException` (404). Маппинг в `bootstrap/app.php`: `render(fn (DomainException $e, Request $r) => ...)` для `api/*` — `{"message": ...}` (для 422 — плюс `errors: {}` в формате Laravel). Код берётся из класса-категории.
3. `MixedChronicleException extends ConflictException` (сообщение то же). Переписать тесты шага 1 с 422 на **409**.
4. `SceneContextService`: `ConflictHttpException` → `SceneNotMutableException extends ConflictException` (текст тот же). Обновить `catch` в `leaveMutableScenes` на новое исключение. Тест архивации из шага 1 остаётся зелёным.
5. `InvalidArgumentException` в сервисах Scope → исключения модуля, наследники `InvalidInputException`, тексты без изменений (например `CharacterNameTakenException`, `InvalidDisciplineLevelException`; имя — по смыслу, один класс может покрывать несколько сообщений).
6. `Biography is unchanged.` → `BiographyUnchangedException`; контроллер ловит **этот класс** (не текст) и возвращает лист без ошибки, как сейчас.
7. `NoChronicleException extends ConflictException`, удалить его `render()`.
8. Удалить из контроллеров Scope `try/catch` + `abort`, ставшие ненужными. Удалить запись baseline `catch.alreadyCaught`.
9. Удалить `WorldEntityAlias` и `WorldRelationType`, если они ещё существуют. В 034a отмечено, что они всё ещё в коде

## Definition of Done

- [ ] все тесты шага 1 зелёные; `MixedChronicleException` → 409 во всех 4 точках
- [ ] `rg "HttpException" app/Scene app/Character app/World app/Chronicle` → пусто
- [ ] `rg "throw new .?InvalidArgumentException" app/Scene app/Character app/World` → пусто
- [ ] `rg "getMessage\(\) !==|getMessage\(\) ===" app/Http` → пусто (кроме `CopilotController` — задача 024a)
- [ ] `rg "abort\(" app/Http/Controllers -g "{CharacterSheet,SceneContext,SceneParticipant}Controller.php"` → пусто или только проверки, отнесённые к 026/026a (перечислить)
- [ ] тексты сообщений ответов не изменились (кроме кода 422 → 409 для mixed chronicle)
- [ ] baseline: запись `catch.alreadyCaught` удалена, новых нет
- [ ] `sail artisan test` — зелёные; `sail pint --test` — чисто; phpstan — OK

## Report

Формат — `.cursor/rules/task-execution.mdc` §5. Дополнительно: таблица шага 1 (было → стало); список созданных классов исключений с категорией.
