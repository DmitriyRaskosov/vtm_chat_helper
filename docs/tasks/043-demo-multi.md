# 043 — Демо-данные с членством

- **Stage:** 3
- **Status:** done
- **Depends on:** 035
- **Risk:** low
- **Part:** 4
- **Разрешённые зависимости:** нет

## Goal

`DemoSeeder` и `demo:reset` создают рассказчика демо как участника демо-хроники, и повторный сброс не оставляет старых членств и приглашений.

## Context

- План §«Этап 3», задача 043. ADR-007, открытый пункт про демо, закрывается здесь.
- Сейчас `DemoSeeder` создаёт пользователя `admin` с `UserRole::Storyteller` (пока колонка есть — это делает 041, не эта задача), хронику «Demo Chronicle», сессию и NPC. Строки `chronicle_members` нет. `DatabaseSeeder` вызывает `ChronicleSeeder` («Моя первая хроника», `created_by` null) и не вызывает `DemoSeeder`. ChronicleSeeder создаёт «Мою первую хронику» без владельца. После 041 (удаление users.role) эта хроника останется без рассказчика — дыра. Здесь: ChronicleSeeder создаёт членство storyteller для пользователя admin (или первого зарегистрированного, если admin нет).
- `demo:reset` (`app/Console/Commands/Demo/ResetCommand.php`) делает `TRUNCATE ... RESTART IDENTITY CASCADE` и затем `DemoSeeder`. Явно добавить `chronicle_invitations` и `chronicle_members` до `chronicles`, чтобы сброс не зависел от того, подхватит ли CASCADE новые FK.
- Если 041 ещё не влита, `users.role` у `admin` выставлять как сейчас. Если колонки уже нет — не писать `role`. Членство storyteller нужно в обоих случаях.
- NPC демо не игроки: членство на них не заводить. Игрока-человека в демо план не требует — не добавлять.
- До 038 глобальный `users.role` ещё пускает рассказчика в API. После 038 без этой задачи демо-рассказчик потеряет права. Зависимость от 038 в плане не стоит; членство всё равно создавать сразу.

## Scope

- `database/seeders/ChronicleSeeder.php` — создать членство storyteller для admin после создания хроники.
- `database/seeders/DemoSeeder.php`
- `app/Console/Commands/Demo/ResetCommand.php` — порядок `TRUNCATE`
- `tests/Feature/DemoResetTest.php`

**Out of scope:**
- приглашения в демо (нечего принимать);
- второй хроники в демо;
- удаление `users.role` (041).

## Steps

1. В транзакции `DemoSeeder` после создания `admin` и «Demo Chronicle» записать `chronicle_members`: `role` storyteller, `joined_at` now, `left_at` null, `user_id` = admin. Повторный сид после truncate не должен падать на unique.
2. В `demo:reset` добавить `TRUNCATE chronicle_invitations` и `TRUNCATE chronicle_members` (RESTART IDENTITY CASCADE) до `TRUNCATE chronicles`.
3. Расширить `DemoResetTest`: после `demo:reset` есть ровно одно членство admin ↔ «Demo Chronicle» с ролью storyteller; приглашений нет; второй прогон команды даёт тот же результат. Прежние проверки демо (сессия, сцена, 5 NPC) не удалять.
4. ChronicleSeeder: после создания «Моей первой хроники» — создать `chronicle_members` (role storyteller, `joined_at` now, `left_at` null) для пользователя admin (или первого зарегистрированного, если admin нет). Проверить: после `migrate:fresh --seed` админ видит эту хронику через `GET /api/chronicles` (036).

## Definition of Done

- [ ] `sail artisan demo:reset` дважды подряд — без ошибки (в контейнере)
- [ ] `DemoResetTest` зелёный, включая второе членство не появляется
- [ ] после `migrate:fresh --seed` админ имеет членство storyteller в «Моей первой хронике».
- [ ] `sail pint --test` — чисто
- [ ] `sail php vendor/bin/phpstan analyse` — без новых ошибок; baseline не вырос. В отчёте `baseline: <до> → <после>`
- [ ] `sail artisan test` — зелёные. В отчёте `tests: <до> → <после>`
- [ ] `sail artisan migrate:fresh --seed` — проходит (демо-сидер при fresh не вызывается; членство «Моей первой хроники» — из `ChronicleSeeder`, демо — только у `demo:reset`)

## Report

Формат — `.cursor/rules/task-execution.mdc` §5. Дополнительно: писался ли `users.role` (зависит от того, влита ли 041).
