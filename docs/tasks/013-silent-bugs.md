# 013 — Тихие баги: строковые литералы вместо enum и находки Larastan

- **Stage:** 1
- **Status:** todo
- **Depends on:** 002, 012
- **Risk:** med
- **Разрешённые зависимости:** нет

## Goal

В коде нет строковых литералов там, где есть enum, и закрыты все записи baseline с пометкой `debt: 013`.

## Context

- Аудит §2.8: `'npc'` (класс дефекта §2.1.1), `'character'` в `WorldRelationService` (~252, 272) и в `Character`, `required_if:character_type,player`.
- Вход — раздел «Находки Larastan» в конце этого файла (дописывается задачей 002).
- `.cursor/rules/backend.mdc` §Enums, `.cursor/rules/http.mdc` §FormRequest.

## Scope

- файлы `app/`, перечисленные в разделе «Находки Larastan», и файлы с литералами из шага 1
- `phpstan-baseline.neon` — только удаление закрытых записей
- `tests/**` — тесты на исправленные находки

**Out of scope:** находки с пометками других задач; повышение level.

## Steps

1. `rg "'(npc|player|character|location|storyteller)'" app` — для каждого вхождения, где сравнивается или присваивается значение поля с enum (`CharacterType`, `WorldEntityType`, `UserRole`, `SceneParticipantRole`), заменить на кейс enum. Литералы в SQL внутри `DB::statement` миграций не трогать.
2. `required_if:character_type,player` и подобные → `Rule::requiredIf(fn () => $this->enum('character_type', CharacterType::class) === CharacterType::Player)` или эквивалент через enum.
3. Для каждой находки `debt: 013`: исправить; если находка — реальный баг поведения, сначала тест, падающий без исправления.
4. Удалить закрытые записи из baseline. Прогон phpstan зелёный.

## Definition of Done

- [ ] `rg "=== '(npc|player)'|!== '(npc|player)'|== '(npc|player)'" app` → пусто
- [ ] `rg "required_if:[a-z_]+,(player|npc)" app` → пусто
- [ ] в baseline нет записей `debt: 013`; общее число записей уменьшилось (до/после — в отчёте)
- [ ] у каждой исправленной находки есть тест или обоснование в отчёте, почему тест не нужен
- [ ] `sail artisan test` — зелёные; `sail pint --test` — чисто; `phpstan analyse` — OK

## Report

Формат — `.cursor/rules/task-execution.mdc` §5. Дополнительно: таблица «находка → исправление → тест/обоснование».

## Находки Larastan

<!-- Дописывается задачей 002. -->
