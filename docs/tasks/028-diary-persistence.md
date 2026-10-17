# 028 — Дневник: общая запись L0/L1 в транзакции и DTO-результаты

- **Stage:** 2
- **Part:** 2
- **Status:** todo
- **Depends on:** 027
- **Risk:** high (основной путь записи дневника)
- **Разрешённые зависимости:** нет

## Goal

Запись L0 и L1 выполняется одним классом persistence, вставка записи и сдвиг курсора участника идут в одной транзакции, а Writer и Summarizer возвращают DTO вместо `array{...}`.

## Context

- `DiaryWriterService::writeEntryForParticipant` (~строки 162–181): `embed()` → сырой `INSERT INTO character_diary_entries` → `$participant->update(['last_diary_message_id' => ...])` — **без транзакции**: при падении после INSERT курсор не сдвинется и запись продублируется.
- `DiarySummarizerService` повторяет схему: LLM → парсер → эмбеддинг → сырой INSERT → `regenerate*`.
- Оба вызывают `chatTurn([...], [...], [])` — третий аргумент `[]` остался от удалённых tool calls (baseline `arguments.count` ×2).
- Публичные методы возвращают массивы: `writeForScene(): array{created, npcs, processed_messages, errors}`, `writeEntryForParticipant(): array{created, processed_messages}` и т. д.
- `RuntimeException` внутри Writer/Summarizer ловятся по каждому NPC и логируются (`diary.write.npc_failed`) — это поведение сохраняется.
- Baseline `debt: 028`: arity ×2, nullsafe в Writer/Summarizer, `DiaryRetrievalService::retrieveWithDiagnostics` return type.
- Страховочная сетка: `tests/Feature/DiaryLlmServicesTest.php` (из задачи 004).

## Scope

- `app/Diary/DiaryEntryRepository.php` (создать; имя можно уточнить): `insertL0(...)`, `insertL1(...)`, `updateContent(...)` — сырой SQL с `VectorLiteral`
- `app/Diary/Data/*.php` (создать): `WriteSceneResult`, `WriteEntryResult`, `SummarizeResult` — `final readonly`
- `app/Diary/Exceptions/*` — исключения LLM-шага дневника вместо `RuntimeException` (ловятся внутри, наружу не выходят)
- `app/Diary/{DiaryWriterService,DiarySummarizerService,DiaryRetrievalService}.php`
- `app/Jobs/{WriteDiaryJob,WriteFinalDiaryJob,SummarizeSceneDiaryJob}.php` — только адаптация к DTO
- `app/Diary/DiaryEntryEditor.php` — использование repository
- `phpstan-baseline.neon`
- `tests/Feature/DiaryLlmServicesTest.php`, `DiaryTest.php`

**Out of scope:** тексты промптов, порог и правила retrieval, `DiaryProvider`, `DiaryPreviewService` (029), формат логов (ключи событий сохранить).

## Steps

1. Прогнать `DiaryLlmServicesTest` — зелёный до правок. Добавить тест: исключение при сдвиге курсора после INSERT → запись не создана (откат), повторный запуск создаёт ровно одну запись.
2. Repository: вставка L0/L1 и обновление — единственное место с `INSERT/UPDATE character_diary_entries`.
3. Writer: `embed()` до транзакции; в транзакции — INSERT + сдвиг курсора. Summarizer — аналогично для своего курсора/признака.
4. Убрать третий аргумент `[]` у `chatTurn`.
5. DTO-результаты для публичных методов; Job-классы читают поля DTO.
6. Закрыть записи baseline `debt: 028`.

## Definition of Done

- [ ] `rg "INSERT INTO character_diary_entries|UPDATE character_diary_entries" app` → только repository
- [ ] `rg "chatTurn\(" -A 20 app/Diary` — ни одного вызова с тремя аргументами (phpstan подтверждает)
- [ ] `rg "@return array\{" app/Diary` → пусто для публичных методов Writer/Summarizer
- [ ] тест отката шага 1 зелёный; `DiaryLlmServicesTest` зелёный без ослабления ассертов
- [ ] записи baseline `debt: 028` удалены
- [ ] `sail artisan test` — зелёные; `sail pint --test` — чисто; phpstan — OK

## Report

Формат — `.cursor/rules/task-execution.mdc` §5. Дополнительно: схема «LLM → эмбеддинг → транзакция» для L0 и L1.
