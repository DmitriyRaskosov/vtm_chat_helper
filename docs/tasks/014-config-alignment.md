# 014 — Конфигурация совпадает с кодом

- **Stage:** 1
- **Status:** todo
- **Depends on:** 004
- **Risk:** med (меняет фактические параметры LLM, если дефолты расходились)
- **Разрешённые зависимости:** нет

## Goal

Все параметры LLM, лимиты и таймауты читаются из `config/`, неиспользуемых ключей и файлов конфигурации нет, дефолты в коде совпадают с config.

## Context

- Аудит §3 «Конфигурация расходится с кодом»:
  - `config/character_sheet.php` нигде не читается;
  - в `config/context.php` читается только `max` секций; `min`, `priority`, `required`, `truncation` не читаются;
  - `diary.write_timeout` не подключён, в задачах дневника `$timeout = 540`;
  - `topic_temperature`: 0.2 в коде, 0.1 в config; `max_input_tokens`: 12000 в коде, 16000 в config;
  - в `DiaryWriterService` и `DiarySummarizerService` зашиты температуры и лимиты (2500/0.7, 0.4).
- `.cursor/rules/llm-context.mdc` §Параметры LLM.

## Scope

- `config/character_sheet.php` (удалить), `config/context.php`, `config/copilot.php`, `config/diary.php`, `config/llm.php`
- `app/Jobs/*.php` — только таймаут
- `app/Diary/DiaryWriterService.php`, `app/Diary/DiarySummarizerService.php` — только чтение параметров из config
- `app/Llm/NpcCopilotService.php`, `app/Context/*.php` (кроме `ContextAssembler`) — только дефолты `config()`
- `.env.example` — только если добавляются переменные
- `tests/**`

**Out of scope:** `ContextAssembler` (если неиспользуемые ключи читаются только им — оставить и указать в отчёте); промпты; изменение значений в config-файлах.

## Steps

1. `rg "config\(" app` — таблица «ключ → дефолт в коде → значение в config».
2. Расхождения дефолтов: **значение в config — источник правды**; дефолт в коде убрать или выровнять. Если поведение в проде меняется (используется дефолт кода, т. к. ключа нет) — указать в отчёте.
3. Подключить `config('diary.write_timeout')` в задачах дневника вместо `540`.
4. Вынести температуры и `max_tokens` Writer/Summarizer в `config/diary.php` с текущими значениями из кода (поведение не меняется).
5. Удалить `config/character_sheet.php` и нечитаемые ключи `context.php` (`rg` по каждому ключу перед удалением).

## Definition of Done

- [ ] `rg "temperature' => [0-9]|=> 0\.[0-9]+|540|2500|12000" app` → пусто (или каждое вхождение объяснено)
- [ ] `rg "character_sheet" app config` → пусто
- [ ] каждый ключ в `config/context.php`, `copilot.php`, `diary.php` читается хотя бы в одном месте `app/`
- [ ] тест: таймаут задачи дневника равен `config('diary.write_timeout')`
- [ ] `sail artisan test` — зелёные; `sail pint --test` — чисто; phpstan — без новых ошибок

## Report

Формат — `.cursor/rules/task-execution.mdc` §5. Дополнительно: таблица из шага 1 и список изменений фактических значений.
