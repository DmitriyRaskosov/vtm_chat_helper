# 024a — Copilot: запись CopilotRequest и ошибки LLM в модуле Llm

- **Stage:** 2
- **Part:** 1
- **Status:** todo
- **Depends on:** 024
- **Risk:** low
- **Разрешённые зависимости:** нет

## Goal

`CopilotController::drafts` только вызывает сервис и отдаёт ответ: создание `CopilotRequest` живёт в модуле Llm, а ошибки LLM выражены классами исключений, а не сравнением текста.

## Context

- `CopilotController::drafts`: после 024 — сцена и персонаж из резолвера; дальше вызов `NpcCopilotService::drafts(...)`, перехват `ConnectionException|RequestException` → 503, `RuntimeException` с текстом `'LLM provider is unavailable.'` → 503, иначе → 502 с текстом исключения; затем `CopilotRequest::create([...])`.
- `NpcCopilotService` бросает `RuntimeException('LLM provider is unavailable.')` (строки ~41, ~64) и `RuntimeException('Could not parse draft replies from the model response.')` (~207).
- `DeepseekChatProvider` бросает `RuntimeException('DeepSeek returned an empty chat response.')`.
- ADR-005: маппинг в `bootstrap/app.php`. Для Copilot нужны коды 503 и 502 — добавить категорию.

## Scope

- `app/Llm/NpcCopilotService.php`
- `app/Llm/Exceptions/LlmUnavailableException.php`, `LlmResponseInvalidException.php` (создать)
- `app/Exceptions/UpstreamException.php` (создать: абстрактная категория с кодом 502/503 в наследнике)
- `bootstrap/app.php` — `withExceptions()`, только добавление категории
- `app/Llm/CopilotDraftService.php` (создать) — или метод в `NpcCopilotService`, если так проще (обосновать)
- `app/Http/Controllers/CopilotController.php`
- `tests/Feature/CopilotTest.php`

**Out of scope:** промпты и сборка контекста; `DeepseekChatProvider` (кроме перевода исключений, если без этого не обойтись — спросить); переименование файла DeepSeek (029); `retrievalQuery` и версия промпта по проходам (отдельно, только с разрешения).

## Steps

1. Характеризация: тесты на 503 (сбой соединения через `FakeChatProvider`, бросающий исключение), 502 (непарсимый ответ модели), 200 (успех, запись `CopilotRequest` с `context_metadata`, `prompt_version`, `builder_version`).
2. `LlmUnavailableException` (503) и `LlmResponseInvalidException` (502) наследуют `UpstreamException`; тексты прежние. `NpcCopilotService` бросает их вместо `RuntimeException`.
3. Сервис `CopilotDraftService::create(...)`: вызывает `NpcCopilotService`, сохраняет `CopilotRequest`, возвращает DTO (`final readonly`) с `copilotRequestId` и `drafts`.
4. Контроллер: резолвер (024) → сервис → `response()->json([...])` той же формы. Никаких `try/catch` — маппинг глобальный.

## Definition of Done

- [ ] `rg "getMessage\(\)" app/Http/Controllers/CopilotController.php` → пусто
- [ ] `rg "CopilotRequest::query\(\)->create|abort" app/Http/Controllers/CopilotController.php` → пусто
- [ ] `rg "new RuntimeException\('LLM provider is unavailable" app` → пусто
- [ ] тесты шага 1: 503, 502, 200 — коды и тексты как до задачи
- [ ] `sail artisan test` — зелёные; `sail pint --test` — чисто; phpstan — OK, baseline не вырос

## Report

Формат — `.cursor/rules/task-execution.mdc` §5.
