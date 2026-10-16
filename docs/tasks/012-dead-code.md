# 012 — Мёртвый код и tool calls

- **Stage:** 1
- **Status:** todo
- **Depends on:** 011
- **Risk:** low
- **Разрешённые зависимости:** нет

## Goal

Из `app/` удалён мёртвый код из аудита §3, инфраструктура tool calls и неиспользуемые импорты.

## Context

- Аудит §3 «Мёртвый код»: `CharacterSheet` (DTO), `setExperience`, `learnPower`, `approve`, `current` (два разных), `findByAlias`, `setSect`, `WorldRelationTypeCatalog::keys`, `CanonClanRelation::relationsOf`; `ContextBuilder::build`, `NpcCopilotService::resolveModelName`, `ChatTurn::toAssistantMessage`; `ToolCall` и ветка tool_calls в `DeepseekChatProvider` (production всегда передаёт пустой список инструментов); неиспользуемые импорты в `Character.php` и `CharacterSheetReader.php`.
- Часть уже удалена задачами 008–011 — это нормально, проверить grep.

## Scope

- `app/Character/CharacterSheet.php` (удалить), `app/Character/*.php` — удаление перечисленных методов
- `app/Models/CanonClanRelation.php`, `app/Models/Character.php`
- `app/Context/ContextBuilder.php` — только удаление `build`, если не вызывается
- `app/Llm/ToolCall.php` (удалить), `app/Llm/DeepseekChatProvider.php`, `app/Llm/ChatProvider.php`, `app/Llm/ChatTurn.php`, `app/Llm/NpcCopilotService.php`
- `tests/**` — только тесты удаляемого кода

**Out of scope:** переименование `DeepseekChatProvider` (этап 2), любые изменения поведения.

## Steps

1. Для каждого элемента списка: `rg "<имя>" app tests routes database resources` — подтвердить отсутствие вызовов (учитывать вызовы по строке, `$this->{...}`, маршруты). Есть вызов — элемент не удалять, указать в отчёте.
2. Для двух `current`: найти оба (`rg "function current" app`), удалить только невызываемые.
3. Удалить `ToolCall`, параметр/ветку tools и `tool_calls` в `ChatProvider`/`DeepseekChatProvider`/`ChatTurn`. Если меняется сигнатура метода интерфейса `ChatProvider` — обновить все реализации и фейк из `tests/Support`.
4. Удалить неиспользуемые `use` во всех файлах `app/` (проверка — phpstan/IDE, не на глаз).

## Definition of Done

- [ ] `rg "ToolCall|tool_calls|setExperience|learnPower|relationsOf|resolveModelName|toAssistantMessage|findByAlias|setSect|class CharacterSheet\b" app tests` → пусто
- [ ] Copilot-тесты зелёные (тело запроса к LLM не содержит `tools`)
- [ ] `sail artisan test` — зелёные; `sail pint --test` — чисто; phpstan — без новых ошибок, записи `debt: 012` удалены из baseline

## Report

Формат — `.cursor/rules/task-execution.mdc` §5. Дополнительно: элементы списка, которые оказались живыми.
