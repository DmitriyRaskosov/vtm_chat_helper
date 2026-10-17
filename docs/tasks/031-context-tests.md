# 031 — Тесты сборки контекста Copilot

- **Stage:** 2
- **Part:** 2
- **Status:** todo
- **Depends on:** 028
- **Risk:** low
- **Разрешённые зависимости:** нет

## Goal

Бюджеты, порядок секций и отбрасывание опциональных секций `ContextAssembler` покрыты прямыми тестами — это страховочная сетка перед задачей 029.

## Context

- Аудит §2.6: assembler и провайдеры проверяются только косвенно через один сценарий Copilot.
- `ContextAssembler` защищён: в этой задаче **не меняется**.
- Обязательные секции: system, identity, scene, storyteller prompt, closing, recent messages. Опциональные: direct_relations, personality, biography, lore, diary. Лимиты — `config('context.assembler.sections.*.max')`.
- Метаданные сборки (включённые/отброшенные секции, причина, токены) пишутся в `CopilotRequest.context_metadata`.
- Есть `tests/Feature/DirectRelationsProviderTest.php`, фейки `FakeChatProvider`/`FakeEmbeddingProvider`.

## Scope

- `tests/Feature/ContextAssemblerTest.php` (создать)
- `tests/Feature/Context/*ProviderTest.php` (создать по необходимости)
- `tests/Support/*` — хелперы сборки сцены с NPC

**Out of scope:** весь `app/`. Если тест вскрывает баг — `markTestSkipped('bug: …')` и описание в отчёте.

## Steps

1. Порядок секций в собранном промпте совпадает с порядком из конфигурации/реестра.
2. Бюджет: при маленьком `max_input_tokens` (через `config()->set`) обязательные секции присутствуют, опциональные отбрасываются, в метаданных причина отбрасывания.
3. Слишком маленький бюджет для обязательных → текущая ошибка (`InvalidArgumentException` с текстом из assembler).
4. Пустая опциональная секция (нет связей) → omitted с причиной `empty` (известное ограничение ADR-002).
5. Секция diary: лимит записей и обрезка по `max`.
6. Проход topics легче прохода reply (меньше секций или токенов) — по метаданным.

## Definition of Done

- [ ] сценарии шагов 1–6 — отдельные тест-методы
- [ ] `git diff app/` → пусто
- [ ] тесты не обращаются к реальным DeepSeek/Ollama
- [ ] `sail artisan test` — зелёные; `sail pint --test` — чисто

## Report

Формат — `.cursor/rules/task-execution.mdc` §5. Дополнительно: найденные баги (если есть `markTestSkipped`).
