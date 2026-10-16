# 015 — Переписать миграции по правилам, сохранив схему

- **Stage:** 1
- **Status:** todo
- **Depends on:** 011
- **Risk:** high (правка всех миграций, `migrate:fresh`)
- **Разрешённые зависимости:** нет

## Goal

Все миграции соответствуют `.cursor/rules/database.mdc` (FK в `Schema::create` ссылающейся таблицы, импорт `DB`, корректный `down()`), а итоговая схема PostgreSQL совпадает с исходной, кроме объяснённых расхождений.

## Context

- ADR-004, `.cursor/rules/database.mdc`.
- Аудит §2.8: FK вне `Schema::create` — `create_copilot_requests` (FK в `messages`), `create_chronicles_and_attach_game_sessions` (в `game_sessions`), `create_characters_and_attach_message_authors` (в `messages`, `copilot_requests`) и ещё пять миграций с отдельным `Schema::table`.
- Аудит §2.1.7: `DB::` без импорта — `2026_09_02_000001_create_messages_table.php`, `2026_09_11_000001_create_chronicles_and_attach_game_sessions.php`.
- Аудит §3: `down()` в `create_canon_lore_tables` удаляет несуществующую `canon_lore_chunks`.
- Инварианты, которые обязаны сохраниться — `docs/ARCHITECTURE.md` §4.

## Scope

- `database/migrations/*.php` — содержимое существующих файлов

**Out of scope:** переименование файлов, создание новых миграций, изменение типов колонок, имён ограничений и индексов, любые изменения схемы по существу.

## Steps

1. Выполнить пункты DoD «до начала работы».
2. Составить таблицу всех FK: «таблица.колонка → цель → файл, где создаётся FK → файл, где создаётся цель».
3. FK, у которых цель создаётся в более раннем файле, перенести в `Schema::create` ссылающейся таблицы.
4. FK, у которых цель создаётся в **более позднем** файле (например, `messages` → `characters`): **остановиться** и показать пользователю список с вариантами (перенос `Schema::create` целевой таблицы в более ранний файл или явное исключение из правила). Не выбирать самому.
5. Добавить `use Illuminate\Support\Facades\DB;` где используется `DB::`. Исправить `down()` во всех файлах: удаляется ровно созданное в `up()`, в обратном порядке.
6. `sail artisan migrate:fresh --seed`, затем пункты DoD «после».

## Definition of Done

**До начала работы (обязательно, иначе задача не выполняется):**
- [ ] `sail artisan migrate:fresh --seed` на текущем коде — успешно
- [ ] `docker compose exec pgsql sh -c 'pg_dump -U "$POSTGRES_USER" --schema-only "$POSTGRES_DB" > /tmp/schema-before.sql'`

**После `migrate:fresh --seed`:**
- [ ] `docker compose exec pgsql sh -c 'pg_dump -U "$POSTGRES_USER" --schema-only "$POSTGRES_DB" > /tmp/schema-after.sql'`
- [ ] `docker compose exec pgsql diff -u /tmp/schema-before.sql /tmp/schema-after.sql` — просмотрен вручную; **каждое** расхождение перечислено в отчёте с объяснением (ожидаемо: только порядок объявления ограничений). Необъяснённое расхождение = задача не выполнена.
- [ ] Триггеры на месте (проверить отдельно в `schema-after.sql` или `\dy`/`pg_trigger`):
  - [ ] `prevent_world_entity_delete` — запрет удаления `world_entities`
  - [ ] `prevent_biography_version_mutation` — неизменяемые версии биографии
  - [ ] `enforce_character_power_level` и `enforce_character_discipline_power_level` — контроль уровней сил
- [ ] Частичные уникальные индексы на месте (проверить отдельно):
  - [ ] `game_sessions_single_active`, `game_sessions_one_active_per_chronicle` — одна активная сессия
  - [ ] `scenes_single_active_per_session` — одна активная сцена в сессии
  - [ ] `scene_participants_current_unique` — одно текущее участие
  - [ ] `world_relations_active_unique` — одна активная связь
  - [ ] частичные индексы `messages` (`WHERE deleted_at IS NULL`) и `character_diary_entries` (`WHERE is_stale = true`)
- [ ] Все CHECK-ограничения из `schema-before.sql` присутствуют в `schema-after.sql` (`grep -c CHECK` по обоим файлам совпадает)
- [ ] `sail artisan migrate:reset`, затем `sail artisan migrate --seed` — без ошибок (проверка `down()`)
- [ ] `rg "Schema::table" database/migrations` → пусто (или каждое вхождение согласовано на шаге 4)
- [ ] `sail artisan test` — зелёные; `sail pint --test` — чисто

## Report

Формат — `.cursor/rules/task-execution.mdc` §5. Дополнительно:
- таблица FK из шага 2 (было → стало);
- полный `diff -u` схемы с объяснением каждого блока;
- вывод проверок триггеров и частичных индексов.
