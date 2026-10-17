# 009 — Связи мира: только чтение, тип связи — enum

- **Stage:** 1
- **Status:** done
- **Depends on:** 008
- **Risk:** high (правка миграций, `migrate:fresh`)
- **Разрешённые зависимости:** нет

## Goal

Связи мира доступны только на чтение для `DirectRelationsProvider`, типы связей заданы enum `WorldRelationKind`, справочник `world_relation_types` и весь API связей удалены.

## Context

- ADR-002 п. 3–5. **Типы связей становятся enum (код), а не данными (таблица)** — осознанный выбор: связей сейчас нет, граф между NPC откладывается.
- ADR-004: миграции правятся на месте, затем `migrate:fresh --seed`.
- Аудит §2.1.2: `WorldRelationException` (нет класса) в `WorldRelationService.php:46,65`, `WorldRelationController.php:14,108`; `WorldRelationTypeException` (нет класса) в `WorldRelationTypeValidator.php`.
- `world_relations.relation` — `string(64)`, FK на `world_relation_types` нет. Ключи типов — в `database/seeders/WorldRelationTypeSeeder.php`.
- Потребитель: `app/Context/Providers/DirectRelationsProvider.php` (`neighbors()` + входящие связи `WorldRelation::query()`).

## Scope

- `app/Http/Controllers/WorldRelationController.php` (удалить)
- `app/Http/Requests/StoreWorldRelationRequest.php` (удалить)
- `routes/api.php` — удалить маршруты `/world/relations*` и `/world/entities/{worldEntity}/neighbors`
- `app/World/WorldRelationTypeCatalog.php`, `app/World/WorldRelationTypeValidator.php` (удалить)
- `app/Models/WorldRelationType.php` (удалить), его фабрика, если есть
- `database/seeders/WorldRelationTypeSeeder.php` (удалить), `database/seeders/DatabaseSeeder.php`
- `database/migrations/2026_09_11_000020_create_world_relation_types_table.php` (удалить файл)
- `app/World/WorldRelationService.php` — оставить только чтение
- `app/Models/WorldRelation.php` — cast `relation` → `WorldRelationKind`
- `app/Enums/WorldRelationKind.php` (создать)
- `app/Context/Providers/DirectRelationsProvider.php` — только адаптация к enum, если нужна (логику секции не менять)
- `tests/Unit/` или `tests/Feature/DirectRelationsProviderTest.php` (создать)

**Out of scope:** `ContextAssembler`, `WorldEntityController` и `WorldEntityService` (задача 010), алиасы (011), текст секции `direct_relations`.

## Steps

1. Снимок схемы внутри контейнера БД (не в репозитории):
   `docker compose exec pgsql sh -c 'pg_dump -U "$POSTGRES_USER" --schema-only "$POSTGRES_DB" > /tmp/schema-before-009.sql'`.
   Сравнение после шага 6: `docker compose exec pgsql diff -u /tmp/schema-before-009.sql /tmp/schema-after-009.sql`.
2. Тест `DirectRelationsProvider` на засеянной связи (сущность A → B, ключ из текущего сидера): секция содержит имя B и тип связи. Зелёный до изменений.
3. Создать `WorldRelationKind` (string-backed) из ключей `WorldRelationTypeSeeder`. Ключи, которые использует код (`rg "'[a-z_]+'" app/World app/Context/Providers/DirectRelationsProvider.php`), обязаны быть кейсами.
4. Удалить контроллер, request, маршруты, каталог, валидатор, модель, сидер, файл миграции; убрать сидер из `DatabaseSeeder`.
5. `WorldRelationService`: оставить `neighbors()` и метод входящих связей (если провайдер использует прямой запрос — оставить как есть). Удалить запись, `end`, ссылки на `WorldRelationException`.
6. `sail artisan migrate:fresh --seed`, повторить снимок в `/tmp/schema-after-009.sql`, сравнить.

## Definition of Done

- [ ] тест `DirectRelationsProvider` на засеянной связи — зелёный
- [ ] `rg "WorldRelationController|WorldRelationType|WorldRelationException|StoreWorldRelationRequest" app routes database tests` → пусто
- [ ] `sail artisan route:list --path=world/relations` → нет маршрутов
- [ ] `sail artisan migrate:fresh --seed` — без ошибок
- [ ] дифф схемы: исчезла только таблица `world_relation_types` (и её индексы/последовательность); `world_relations_active_unique` и CHECK `world_relations` на месте
- [ ] `sail artisan test` — зелёные; `sail pint --test` — чисто; phpstan — без новых ошибок (записи baseline `debt: 009` удалены)
- [ ] до работы зафиксирован размер baseline (`rg -c "message:" phpstan-baseline.neon`)
- [ ] `sail php vendor/bin/phpstan analyse --generate-baseline=phpstan-baseline.neon`, затем `sail php vendor/bin/phpstan analyse` — OK
- [ ] размер baseline не вырос относительно «до»; если вырос — задача не закрывается, разобрать причину
- [ ] в отчёте: `baseline: <до> → <после>` (например `baseline: 185 → 142`)

## Report

Формат — `.cursor/rules/task-execution.mdc` §5. Дополнительно:
- список кейсов `WorldRelationKind`;
- дифф схемы (только строки `-`/`+`).
