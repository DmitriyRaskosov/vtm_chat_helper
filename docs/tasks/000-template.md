# NNN — Краткое название

- **Stage:** 1 | 2 | 3
- **Status:** todo | in-progress | done | blocked
- **Depends on:** NNN, NNN | —
- **Risk:** low | med | high
- **Разрешённые зависимости:** нет | `vendor/package` (require-dev)

## Goal

Одно предложение: что должно стать правдой после задачи.

## Context

- Аудит: `docs/audit-2026-10-16.md` §X.Y
- ADR: `docs/adr/NNN-*.md`
- Факты, которые исполнитель должен знать заранее (строки, имена, ловушки).

## Scope

Можно менять (создавать, удалять) только эти файлы:
- `path/to/file.php`
- `tests/Feature/SomeTest.php`

**Out of scope** (не трогать, даже если хочется):
- …

## Steps

1. …
2. …
3. …

## Definition of Done

- [ ] `sail pint --test` — чисто
- [ ] `sail artisan test` — зелёные
- [ ] `sail php vendor/bin/phpstan analyse` — без новых ошибок (после задачи 002)
- [ ] `cd frontend; npm run build` — без ошибок (если менялся фронт)
- [ ] grep-проверки: `…` → пусто
- [ ] специфичные пункты задачи
- [ ] `docs/Scope.md` обновлён (если менялись видимые границы продукта)

## Report

Формат — `.cursor/rules/task-execution.mdc` §5. Дополнительно для этой задачи:
- …
