# 016 — Сидеры: один приём для канона, транзакция для демо

- **Stage:** 1
- **Status:** done
- **Depends on:** 015
- **Risk:** med
- **Разрешённые зависимости:** нет

## Goal

Канонические сидеры используют один идемпотентный приём, `DemoSeeder` работает в транзакции, `demo:reset` покрыт тестом, README и `Scope.md` описывают демо так, как оно работает.

## Context

- Аудит §3: в сидерах пять приёмов (`upsert` на модели, `DB::table()->upsert`, `insertOrIgnore`, `firstOrCreate`, `create`); README (строка ~90) утверждает, что `DatabaseSeeder` запускает `DemoSeeder` при `local` — в коде этого нет; `DemoSeeder` без транзакций (§2.3).
- Демо по факту: пользователь `admin` (рассказчик), «Demo Chronicle», «Demo Session», сцена «Встреча на пустыре», 5 NPC-участников (Абрахам Леру, Август Сент-Клер, Гидеон Эш, Ильзе Фогель, Порижана), сообщений нет.
- `.cursor/rules/database.mdc` §Сидеры.

## Scope

- `database/seeders/Canon*Seeder.php`, `ChronicleSeeder.php`, `DemoSeeder.php`, `DatabaseSeeder.php`
- `app/Console/Commands/` — только команда `demo:reset`, если для теста нужна правка (без изменения поведения)
- `tests/Feature/DemoResetTest.php` (создать)
- `README.md` — только раздел про сидеры/демо
- `docs/Scope.md` — только упоминания демо

**Out of scope:** состав демо-данных (персонажи, тексты); миграции; канонические данные по существу.

## Steps

1. Тест `demo:reset` (до изменений): после команды есть хроника, сессия, сцена «Встреча на пустыре», 5 NPC с указанными именами; канон и пользователи не удалены; повторный запуск идемпотентен (число сущностей не растёт).
2. Канонические сидеры: единый приём — `Model::query()->upsert($rows, uniqueBy: ['slug'], update: [...])` (или `DB::table()->upsert` во всех — выбрать один и применить везде). Повторный `db:seed` не меняет число строк.
3. `DemoSeeder`: тело `run()` в `DB::transaction`.
4. README: привести описание к коду (как запускается демо, что создаётся). `Scope.md`: проверить, что раздел демо совпадает с фактом.

## Definition of Done

- [ ] тест `demo:reset` — зелёный, включая идемпотентность
- [ ] `sail artisan db:seed` дважды подряд — число строк в `canon_*` не меняется
- [ ] `rg "insertOrIgnore|firstOrCreate" database/seeders/Canon*` → пусто
- [ ] `rg "DB::transaction" database/seeders/DemoSeeder.php` → есть
- [ ] README не утверждает того, чего нет в коде
- [ ] `sail artisan test` — зелёные; `sail pint --test` — чисто; phpstan — без новых ошибок

## Report

Формат — `.cursor/rules/task-execution.mdc` §5. Дополнительно: выбранный приём upsert и почему.
