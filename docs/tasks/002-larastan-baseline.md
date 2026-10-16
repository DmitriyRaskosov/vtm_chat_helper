# 002 — Larastan level 5 с ограниченным baseline

- **Stage:** 1
- **Status:** todo
- **Depends on:** 001
- **Risk:** med
- **Разрешённые зависимости:** `larastan/larastan` (require-dev, последняя стабильная, совместимая с Laravel 13)

## Goal

Larastan level 5 подключён, прогон зелёный, baseline содержит не более 50 записей, и каждая запись — либо долг со ссылкой на задачу, либо обоснованное исключение.

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
2. `phpstan.neon`: `includes` — расширение Larastan и `phpstan-baseline.neon`; `paths: [app]`; `level: 5`. Без `ignoreErrors` в основном конфиге.
3. `sail php vendor/bin/phpstan analyse --generate-baseline` — получить baseline.
4. Посчитать записи baseline (элементы `ignoreErrors`, каждая со своим `count`).
   - **Если записей больше 50 — остановиться.** Задача не закрывается. В отчёте — сгруппированный список находок и предложение, какие рефакторинг-задачи нужны до baseline. Ничего не исправлять.
5. Если записей ≤ 50 — над каждой записью в `phpstan-baseline.neon` комментарий одного из двух видов:
   - `# debt: NNN — краткое описание` (задача, которая её закроет: 009, 010, 011, 012, 013, 014, 017);
   - `# exception: причина` (ложное срабатывание или осознанное решение, с объяснением).
   Запись без комментария не допускается.
6. Дописать в конец `docs/tasks/013-silent-bugs.md` раздел «Находки Larastan» — записи с пометкой `debt: 013`.
7. Убедиться, что прогон без `--generate-baseline` зелёный.

## Definition of Done

- [ ] `sail php vendor/bin/phpstan analyse` — `[OK] No errors`
- [ ] в `phpstan-baseline.neon` ≤ 50 записей (`rg -c "message:" phpstan-baseline.neon`)
- [ ] у каждой записи baseline есть комментарий `# debt: NNN` или `# exception: …` (число комментариев = числу записей)
- [ ] находки из аудита §2.1.2–3 присутствуют в baseline (проверка, что анализ видит World)
- [ ] `sail pint --test` — чисто
- [ ] `sail artisan test` — зелёные
- [ ] в `composer.json` добавлен только `larastan/larastan`

## Report

Формат — `.cursor/rules/task-execution.mdc` §5. Дополнительно:
- число записей baseline и разбивка по задачам (`debt: 009` — N, …, `exception` — N);
- полный список `exception` с обоснованием;
- если остановились на шаге 4 — сгруппированный список находок вместо остального.
