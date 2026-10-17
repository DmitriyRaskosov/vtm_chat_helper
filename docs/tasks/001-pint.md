# 001 — Pint: конфиг и разовое форматирование

- **Stage:** 1
- **Status:** done
- **Depends on:** —
- **Risk:** low
- **Разрешённые зависимости:** нет (`laravel/pint` уже в `require-dev`)

## Goal

Весь PHP-код отформатирован Pint по preset `laravel`, `sail pint --test` проходит чисто, а из `composer.json` убрано упоминание Pest.

## Context

- ADR-006 (Pint — да, Pest — нет).
- Аудит §3: «В `composer.json` разрешён плагин Pest, хотя Pest не используется».
- `laravel/pint ^1.27` уже установлен, `pint.json` в корне нет.
- Pest-плагин присутствует только в `config.allow-plugins` (`"pestphp/pest-plugin": true`), не в `require`.

## Scope

- `pint.json` (создать)
- `composer.json` — только удаление строки `"pestphp/pest-plugin": true` из `config.allow-plugins`
- любые `*.php` в `app/`, `bootstrap/`, `config/`, `database/`, `routes/`, `tests/` — **только** изменения, которые вносит сам Pint

**Out of scope:**
- ручные правки PHP-кода, даже очевидные (мёртвые импорты, опечатки);
- `composer.lock`, `vendor/`, `frontend/`;
- добавление `declare(strict_types=1)` (правило `declare_strict_types` не включать).

## Steps

1. Прогнать `sail artisan test`, записать число тестов (ожидается 54 feature + 1 unit).
2. Создать `pint.json`:
   ```json
   {
       "preset": "laravel"
   }
   ```
   Других правил не добавлять.
3. Удалить `"pestphp/pest-plugin": true` из `composer.json` → `config.allow-plugins`. Убедиться, что JSON валиден (`sail composer validate --no-check-publish`).
4. Запустить `sail pint` (полное форматирование).
5. Просмотреть `git diff --stat`: изменены только PHP-файлы, `pint.json`, `composer.json`.
6. Прогнать `sail artisan test`.

## Definition of Done

- [ ] `sail pint --test` — чисто
- [ ] `sail artisan test` — зелёные, число тестов совпадает с шагом 1
- [ ] `sail composer validate --no-check-publish` — без ошибок
- [ ] `rg "pest" composer.json` → пусто
- [ ] `rg "strict_types" app tests database routes config bootstrap` → пусто
- [ ] в `git diff` нет изменений, кроме форматирования PHP, `pint.json` и одной строки `composer.json`
- [ ] `composer.lock` не изменён

## Report

Формат — `.cursor/rules/task-execution.mdc` §5. Дополнительно:
- число изменённых PHP-файлов (`git diff --stat | tail -1`);
- если Pint изменил что-то кроме пробелов, порядка импортов и скобок (например, смысл выражения) — перечислить файлы отдельно.
- Пользователю: этот коммит стоит добавить в `.git-blame-ignore-revs` (файл не создавать).
