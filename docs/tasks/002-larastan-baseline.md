# 002 — Larastan level 5 с baseline

- **Stage:** 1
- **Status:** done
- **Depends on:** 001
- **Risk:** med
- **Разрешённые зависимости:** `larastan/larastan` (require-dev, последняя стабильная, совместимая с Laravel 13)

## Goal

Larastan level 5 подключён, прогон зелёный, текущие находки зафиксированы в baseline. Сжатие baseline — по задачам 009–017; финальный gate этапа 1 (≤50 записей) — задача 017.

## Context

- ADR-006 (Larastan — да).
- Аудит §2.1.1–3: несуществующие классы `WorldRelationException`, `WorldRelationTypeException`, `CannotDeleteWorldEntityException`, `WorldEvent`; несуществующие методы `assertSectFaction`, `coterie()`, `circle()`; `Location::` без импорта в `WorldEntityService`. Larastan обязан их найти — это проверка, что конфиг рабочий.
- Список находок — вход для задачи 013; находки по World — для задач 009, 010, 012.

## Scope

- `composer.json`, `composer.lock` — только добавление `larastan/larastan` в `require-dev`
- `phpstan.neon` (создать)
- `phpstan-baseline.neon` (создать)
- `docs/tasks/013-silent-bugs.md` — только дописать раздел «Находки Larastan» в конец

**Out of scope:**
- любые правки PHP-кода в `app/`, `tests/`, `database/` (исправления — задачи 009–013);
- level выше 5; включение `tests/` в анализ.

## Steps

1. `sail composer require --dev larastan/larastan`.
2. `phpstan.neon`: `includes` — расширение Larastan и `phpstan-baseline.neon`; `paths`: `app`, `database`, `routes`; `level: 5`. Без `ignoreErrors` в основном конфиге.
3. `sail php vendor/bin/phpstan analyse --generate-baseline` — получить baseline.
4. Зафиксировать число записей baseline (`rg -c "message:" phpstan-baseline.neon`). Комментарии `# debt:` / `# exception:` на каждую запись — при сжатии в задачах 009–017, не обязательны при первичном внедрении.
5. Убедиться, что прогон без `--generate-baseline` зелёный.

## Definition of Done

- [x] `sail php vendor/bin/phpstan analyse` — `[OK] No errors`
- [x] `phpstan-baseline.neon` создан и подключён; размер зафиксирован в отчёте задачи
- [x] находки из аудита §2.1.2–3 присутствуют в baseline (проверка, что анализ видит World)
- [x] `sail pint --test` — чисто
- [x] `sail artisan test` — зелёные
- [x] в `composer.json` добавлен только `larastan/larastan`

## Report

Формат — `.cursor/rules/task-execution.mdc` §5.

**Итог:** Принят baseline = 185 записей. Политика сжатия — в ADR-006. Финальный gate этапа 1 (≤50) — в task 017.

- `phpstan analyse`: OK (0 errors вне baseline)
- `artisan test`: 55 passed
- `pint --test`: OK
- 245 срабатываний сгруппированы в 185 записей `ignoreErrors`
