# 011 — Удаление алиасов, уникальность имени индексом

- **Stage:** 1
- **Status:** todo
- **Depends on:** 010
- **Risk:** high (правка миграций, `migrate:fresh`)
- **Разрешённые зависимости:** нет

## Goal

Алиасов сущностей нет; уникальность имени в хронике обеспечивается функциональным уникальным индексом и проверкой в сервисе, переименование в занятое имя даёт 422.

## Context

- ADR-002 п. 7.
- Сейчас уникальность — `world_entity_aliases_chronicle_normalized_unique (chronicle_id, normalized_alias)`, без учёта типа сущности. Проверка в `CharacterIdentityService::rename` (~76–99) через алиасы. Нормализация — `app/World/AliasNormalizer.php`.
- Таблица `world_entity_aliases` создаётся в `2026_09_11_000012_create_world_entities_and_aliases_tables.php` (там же частичный индекс `world_entity_aliases_one_canonical` — удаляется вместе с таблицей).

## Scope

- `database/migrations/2026_09_11_000012_create_world_entities_and_aliases_tables.php` — удалить `world_entity_aliases`; добавить уникальный индекс `world_entities_chronicle_name_unique` на `(chronicle_id, lower(canonical_name))` через `DB::statement`
- `app/Models/WorldEntityAlias.php`, `app/World/AliasNormalizer.php`, `app/Enums/WorldEntityAliasType.php` (удалить)
- `app/Models/WorldEntity.php` — удалить связь с алиасами
- `app/World/WorldEntityService.php` — создание identity без алиаса, проверка занятости имени
- `app/Character/CharacterIdentityService.php` — `rename` без алиасов
- `database/seeders/DemoSeeder.php`, фабрики — только если создают алиасы
- `tests/Feature/CharacterTest.php`

**Out of scope:** публичные сигнатуры `rename`, форма ответа, текст сообщения об ошибке (сохранить текущий).

## Steps

1. Снимок схемы `/tmp/schema-before-011.sql` в контейнере `pgsql` (команда — задача 009, шаг 1).
2. Тест (зелёный до изменений): переименование персонажа в имя другого персонажа той же хроники → 422 с текущим текстом ошибки; в другом регистре — тоже 422; переименование в свободное имя → 200.
3. Прочитать `AliasNormalizer`. Если он делает больше, чем `lower` (trim, схлопывание пробелов, `ё→е`) — `trim` и схлопывание пробелов перенести в сервис перед сохранением `canonical_name`; прочие преобразования перечислить в отчёте как изменение поведения.
4. Миграция: удалить таблицу алиасов из `up()`/`down()`, добавить функциональный уникальный индекс.
5. Сервис: проверка `exists` по `lower(canonical_name)` в хронике (исключая саму сущность) → доменная 422-ошибка тем же способом, что сейчас. Нарушение индекса (`UniqueConstraintViolationException`) при гонке → та же ошибка.
6. Удалить модель, нормализатор, enum, все использования.
7. `migrate:fresh --seed`, снимок `/tmp/schema-after-011.sql`, сравнить `diff -u`.

## Definition of Done

- [ ] `rg "WorldEntityAlias|AliasNormalizer|normalized_alias|world_entity_aliases|findByAlias" app database tests` → пусто
- [ ] тесты шага 2 — зелёные
- [ ] дифф схемы: исчезли `world_entity_aliases` и её индексы; добавлен `world_entities_chronicle_name_unique`
- [ ] `sail artisan migrate:fresh --seed` — без ошибок (демо-имена уникальны)
- [ ] `sail artisan test` — зелёные; `sail pint --test` — чисто; phpstan — без новых ошибок
- [ ] до работы зафиксирован размер baseline (`rg -c "message:" phpstan-baseline.neon`)
- [ ] `sail php vendor/bin/phpstan analyse --generate-baseline=phpstan-baseline.neon`, затем `sail php vendor/bin/phpstan analyse` — OK
- [ ] размер baseline не вырос относительно «до»; если вырос — задача не закрывается, разобрать причину
- [ ] в отчёте: `baseline: <до> → <после>` (например `baseline: 185 → 142`)

## Report

Формат — `.cursor/rules/task-execution.mdc` §5. Дополнительно:
- что делал `AliasNormalizer` и что из этого сохранено;
- дифф схемы.
