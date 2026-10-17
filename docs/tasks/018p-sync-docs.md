# 018p — Синхронизация документов и baseline после этапа 1

- **Stage:** 2
- **Part:** 1
- **Status:** done
- **Depends on:** 017
- **Risk:** low
- **Разрешённые зависимости:** нет
- **Исполнитель:** Composer — с человеческим ревью после

## Goal

Документы, правила и `phpstan-baseline.neon` описывают состояние после этапа 1, а каждая запись baseline привязана к задаче этапа 2 или обоснованному исключению.

## Context

- Ревью этапа 1: в baseline нет ни одного `# debt: NNN` / `# exception:` (DoD задачи 002 нарушен после перегенерации `--generate-baseline`).
- `docs/Scope.md` §Tests: 54 теста и `ExampleTest`; по факту 84 (80 Feature + 4 Unit), `ExampleTest` удалён.
- `.cursor/rules/testing.mdc`: `TODO(004)` не закрыт; `TODO(003)` закрыт частично. `.cursor/rules/llm-context.mdc`: `TODO(014)`, `TODO(029)` (переноса `TokenEstimator` не будет).
- ADR-001 п. 4 обновлён: `TokenEstimator` остаётся в `Context`. `docs/ARCHITECTURE.md` §3 ещё говорит «`TokenEstimator` (после задачи 029)» в `Llm`.
- Решения ревью: `MixedChronicleException` → 409; `chronicle_id` во входе удаляется в 019.

## Scope

- `docs/Scope.md` — разделы Tests, World, дата «Что работает сейчас»
- `docs/ARCHITECTURE.md` — §2 (правило про `App\Context`), §3 (Llm, Context), §7
- `.cursor/rules/testing.mdc`, `.cursor/rules/llm-context.mdc`, `.cursor/rules/task-execution.mdc`
- `AGENTS.md` — только раздел «Команды» и «Карта `app/`»
- `phpstan-baseline.neon` — только комментарии над записями, без изменения самих записей

**Out of scope:** код приложения, тесты, `phpstan.neon`, ADR (кроме уже внесённого в ADR-001), файлы задач.

## Steps

1. `Scope.md`: Tests → 84 (перечислить классы и число методов по факту `rg -c "function test_" tests`); обновить дату раздела; World — как сейчас («identity + read-only связи, `WorldRelationKind` — 13 унаследованных значений, не используются»).
2. `ARCHITECTURE.md`: `TokenEstimator` остаётся в `Context` (ссылка на ADR-001 п. 4); в §2 добавить исключение «`App\Observers\MessageObserver` → `App\Context\TokenEstimator`»; §7 — категории исключений как в задаче 018.
3. `testing.mdc`: закрыть `TODO(003)` (имена `FakeChatProvider`, `FakeEmbeddingProvider`, `CreatesDiaryEntries`, `CreatesSceneMessages`, хелперы `fakeChat()`/`fakeEmbeddings()` — по факту `tests/`), закрыть `TODO(004)` (образец — `tests/Feature/DiaryLlmServicesTest.php`).
4. `llm-context.mdc`: закрыть `TODO(014)` (актуальные ключи `config/copilot.php`, `context.php`, `diary.php`); `TODO(029)` переписать: «`DiaryPreviewService` → Context; `TokenEstimator` остаётся в Context».
5. `task-execution.mdc` §3: добавить запрет «**НЕ** запускать `phpstan analyse --generate-baseline`; закрытые записи baseline удаляются вручную вместе с комментарием; новые записи — только с разрешения задачи».
6. `phpstan-baseline.neon`: над каждой из 33 записей — `# debt: NNN — кратко` (задачи этапа 2 по таблице ниже) или `# exception: причина`.

| Записи | Задача |
|---|---|
| `CharacterSheetController` (dead catch, phpDoc) | 018, 026 |
| `ChatController` (abort_unless, array_key_exists ×2) | 025b |
| `DiaryEntryController` (`$title`, `$value`) | 027 |
| `DiaryWriterService`, `DiarySummarizerService` (arity, nullsafe) | 028 |
| `DiaryRetrievalService` (return type) | 028 |
| `DiaryPreviewService`, `SceneProvider` (`$value` on string) | 029 / exception |
| `DirectRelationsProvider` (6 записей) | exception: ветки метаданных под будущий граф (ADR-002) |
| `SceneContextController`, `SceneParticipantController` | 026a |
| `Message` (2) | 029 |
| `Character*Service` (5) | 026 |
| `ContextRequest`, `OllamaEmbeddingProvider`, `routes/web.php` | exception или 028 — решить при разметке |

## Definition of Done

- [ ] `rg -c "^\s*-\s*$" phpstan-baseline.neon` (число записей) = `rg -c "# (debt|exception):" phpstan-baseline.neon`
- [ ] `sail php vendor/bin/phpstan analyse` — OK (комментарии не ломают neon)
- [ ] `rg "TODO\((00[0-9]|01[0-7])\)" .cursor/rules` → пусто
- [ ] `rg "TokenEstimator" docs/ARCHITECTURE.md` — только «остаётся в Context»
- [ ] `Scope.md` §Tests совпадает с `rg -c "function test_" tests`
- [ ] `git diff --stat` — только файлы из Scope

## Report

Формат — `.cursor/rules/task-execution.mdc` §5. Дополнительно: итоговая таблица «запись baseline → debt/exception».
