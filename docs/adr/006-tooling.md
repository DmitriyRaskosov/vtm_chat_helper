# ADR-006. Инструменты: PHPUnit, Pint, Larastan, без strict_types

- **Status:** Accepted
- **Date:** 2026-10-16

## Context

Нужны инструменты, которые ловят несуществующие классы и методы, сравнения, всегда истинные или ложные, и стилевой шум в диффах. `declare(strict_types=1)` в проекте не используется.

Карта проекта — `AGENTS.md`. Границы продукта — `docs/Scope.md`.

## Decision

1. **PHPUnit — да, Pest — нет.** Тесты — классы PHPUnit. Pest не подключаем.
2. **Pint — да**, preset `laravel`, `pint.json` в корне. Проверка: `./vendor/bin/pint --test`.
3. **Larastan — да** (`larastan/larastan`), `phpstan.neon`, level 5, baseline в `phpstan-baseline.neon`.
   - Размер baseline только уменьшается или остаётся тем же. Рост — регрессия.
   - Сжимать baseline, удаляя записи без реального исправления, запрещено.
   - Новая находка либо исправляется, либо добавляется в baseline с комментарием, зачем она оставлена.
4. **`declare(strict_types=1)` — нет.** Директива влияет только на приведение скаляров в файле, где она объявлена. Вход в проекте — HTTP и PDO; в непокрытых местах она превратит тихую деградацию в `TypeError`. Строгость дают типы параметров и возвращаемых значений плюс Larastan.

## Consequences

- Перед коммитом зелёные `pint --test`, `artisan test` и `phpstan analyse`.
- Ошибки вроде `int !== 'npc'` ловятся статически.
