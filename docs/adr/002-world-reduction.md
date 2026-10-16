# ADR-002. Сокращение модуля World

- **Status:** Accepted
- **Date:** 2026-10-16

## Context

Модуль World задумывался как граф сущностей мира: типы `faction`, `concept`, `coterie`, `circle`, события, алиасы, справочник типов связей, CRUD через 9 маршрутов. По факту (аудит §2.1.2–3, §2.5):
- экран «Мир» из UI убран, фронт вызывает только `GET`/`POST /world/entities` ради гавани;
- в коде есть ссылки на несуществующие классы (`WorldRelationException`, `WorldRelationTypeException`, `CannotDeleteWorldEntityException`, `WorldEvent`) и методы (`assertSectFaction`, `coterie()`, `circle()`);
- реально нужны: общий PK `world_entities` для персонажей и локаций и чтение связей для секции Copilot `direct_relations`.

## Decision

1. `world_entities` остаётся как identity: `id`, `chronicle_id`, `entity_type`, `canonical_name`. Общий PK с `characters` и `locations`, составные FK и CHECK сохраняются. Триггер запрета удаления сохраняется.
2. `WorldEntityType` сокращается до `Character` и `Location`. Таблицы `factions`, `concepts` удаляются из миграций (задача 010).
3. Удаляются: `WorldEntityController`, `WorldRelationController`, все маршруты `/world/*`, `WorldRelationTypeCatalog`, `WorldRelationTypeValidator`, модель и таблица `world_relation_types` с сидером (задачи 009, 010).
4. `WorldRelationService` сокращается до чтения: `neighbors()` и входящие связи — для `DirectRelationsProvider`.
5. **Типы связей становятся enum (код), а не данными (таблица).** Ключ `world_relations.relation` типизируется enum `WorldRelationKind`. Это осознанный выбор: сейчас связей нет, граф между NPC откладывается. Если понадобится расширять типы без миграции — вернёмся к таблице.
6. Гавань персонажа удаляется полностью: backend (`CharacterPlaceService`, `WorldRelationService::setHaven/activeHaven`, `UpdateCharacterPlaceRequest`) и frontend (блок в `SheetPlaceSection.vue`) (задача 008). Секта берётся только из `characters.sect_id`; авто-синхронизация связи `member_of` удаляется.
7. Алиасы удаляются: `world_entity_aliases`, `WorldEntityAlias`, `AliasNormalizer`, `WorldEntityAliasType`. Уникальность имени в хронике — функциональный уникальный индекс по `lower(canonical_name)` в пределах хроники плюс проверка в сервисе с понятной ошибкой 422 (задача 011).
8. Локации остаются в схеме. `scene_contexts.location_entity_id` не заполняется из UI (локация сцены — свободный текст); это не меняется.

## Consequences

- **Известное ограничение.** После удаления API и авто-синхронизации секты записывать `world_relations` некому: `DemoSeeder` связей не создаёт. Секция `direct_relations` будет стабильно попадать в omitted с причиной `empty`, пока не появится путь записи. Провайдер сохраняется и покрывается unit-тестом на засеянной связи (задача 009), чтобы не сломаться к моменту, когда граф вернётся.
- Фронтенду после задачи 008 не нужен ни один World-маршрут.
- Новые типы связей требуют правки enum (и миграции, если в БД есть CHECK на значения).
- Возврат к графу мира — новый ADR.
