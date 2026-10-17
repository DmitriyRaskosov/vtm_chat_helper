# 010 — Сущности мира: только identity персонажей и локаций

- **Stage:** 1
- **Status:** done
- **Depends on:** 009
- **Risk:** high (правка миграций, `migrate:fresh`)
- **Разрешённые зависимости:** нет

## Goal

`world_entities` служит только identity для персонажей и локаций: API сущностей удалён, `WorldEntityType` содержит два кейса, таблиц `factions` и `concepts` нет.

## Context

- ADR-002 п. 1–3.
- Аудит §2.1.2–3: `CannotDeleteWorldEntityException` (нет класса) в `Models/WorldEntity.php:34`; `WorldEvent` (нет модели) в `WorldEntity.php:124–127`; `updateSectFaction` (`WorldEntityService.php:482–503`) вызывает несуществующие `assertSectFaction`, `coterie()`, `circle()`; `Location::` без импорта (~строка 363).
- Таблицы `factions`, `concepts` создаются в `2026_09_11_000013_create_typed_world_entity_tables.php` (там же `locations` — она остаётся).
- Фронт после задачи 008 не вызывает `/world/*`.

## Scope

- `app/Http/Controllers/WorldEntityController.php` (удалить)
- `app/Http/Requests/StoreWorldEntityRequest.php`, `UpdateWorldEntityRequest.php` (удалить)
- `routes/api.php` — удалить маршруты `/world/entities*`
- `app/World/WorldEntityService.php` — оставить создание identity и `assertSameChronicle` (+ то, что вызывают `Character/` и `Scene/`)
- `app/Models/WorldEntity.php`
- `app/Enums/WorldEntityType.php` — оставить `Character`, `Location`
- `app/Enums/FactionStatus.php` (удалить, если используется только фракциями)
- `database/migrations/2026_09_11_000013_create_typed_world_entity_tables.php` — удалить `factions`, `concepts`
- тесты, ссылающиеся на удаляемое

**Out of scope:** алиасы (задача 011), `locations`, `characters`, триггер `prevent_world_entity_delete`.

## Steps

1. Снимок схемы `/tmp/schema-before-010.sql` в контейнере `pgsql` (команда — задача 009, шаг 1).
2. `rg "WorldEntityService" app` — составить список методов, которые реально вызываются вне `World/` и `WorldEntityController`. Только они остаются.
3. Удалить контроллер, requests, маршруты. Урезать сервис: удалить `updateSectFaction`, `renameEntity` (если не вызывается вне контроллера), всё, что относится к faction/concept/coterie/circle/event.
4. `WorldEntity`: убрать ссылку на `CannotDeleteWorldEntityException` (запрет удаления обеспечивает триггер) и связь с `WorldEvent`, связи с фракциями/концептами.
5. `WorldEntityType`: оставить `Character`, `Location`. Удалить `FactionStatus`, если у него не осталось потребителей.
6. Миграция: удалить создание `factions`, `concepts` и их CHECK из `up()` и `down()`.
7. `sail artisan migrate:fresh --seed`, снимок `/tmp/schema-after-010.sql`, сравнить `diff -u`.

## Definition of Done

- [ ] `rg "WorldEntityController|StoreWorldEntityRequest|UpdateWorldEntityRequest|CannotDeleteWorldEntityException|WorldEvent|updateSectFaction|assertSectFaction|Coterie|Circle|Faction|Concept" app routes database tests` → пусто
- [ ] `sail artisan route:list --path=world` → нет маршрутов
- [ ] создание персонажа через API работает (существующий тест зелёный)
- [ ] тест: `DELETE FROM world_entities` для существующей сущности падает с ошибкой триггера (добавить, если нет)
- [ ] дифф схемы: исчезли только `factions`, `concepts` и их ограничения; триггер `prevent_world_entity_delete` на месте
- [ ] `sail artisan test` — зелёные; `sail pint --test` — чисто; phpstan — без новых ошибок (записи `debt: 010` удалены из baseline)
- [ ] до работы зафиксирован размер baseline (`rg -c "message:" phpstan-baseline.neon`)
- [ ] `sail php vendor/bin/phpstan analyse --generate-baseline=phpstan-baseline.neon`, затем `sail php vendor/bin/phpstan analyse` — OK
- [ ] размер baseline не вырос относительно «до»; если вырос — задача не закрывается, разобрать причину
- [ ] в отчёте: `baseline: <до> → <после>` (например `baseline: 185 → 142`)

## Report

Формат — `.cursor/rules/task-execution.mdc` §5. Дополнительно:
- оставшиеся публичные методы `WorldEntityService` и их потребители;
- дифф схемы.
