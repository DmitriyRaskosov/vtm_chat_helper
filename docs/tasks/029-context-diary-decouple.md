# 029 — Развязка Context ↔ Diary, MessageObserver, имя DeepSeekChatProvider

- **Stage:** 2
- **Part:** 2
- **Status:** done
- **Depends on:** 031
- **Risk:** med
- **Разрешённые зависимости:** нет

## Goal

Модуль Diary не импортирует `App\Context\*`, лимит дневника задан в одном месте, хук `Message::booted` заменён Observer-ом с DI, а класс и файл провайдера DeepSeek называются `DeepSeekChatProvider`.

## Context

- ADR-001 п. 4 (обновлён): `TokenEstimator` **остаётся в `App\Context`** и не переносится. `MessageObserver` импортирует `App\Context\TokenEstimator` — осознанное исключение. `ContextAssembler` в этой задаче не меняется.
- `App\Diary\DiaryPreviewService` импортирует `Context\DiaryRenderer`, `LineTrimmer`, `TokenEstimator` — цикл. Он повторяет путь `DiaryProvider`; лимит истории — `config('copilot.history_limit', 15)`, а провайдер берёт `historyLimit()` из запроса.
- Лимит «3 записи» дважды: `DiaryProvider::LIMIT` и `DiaryRetrievalService::MAX_ENTRIES`.
- `Message::booted` → `static::saving(...)` с `app(TokenEstimator::class)`; считает `token_estimate`, `token_estimator_version`, если изменился `body` или оценки нет. Baseline: 2 записи `Message` (`debt: 029`).
- Файл `app/Llm/DeepseekChatProvider.php` (регистр на диске проверить), класс `DeepseekChatProvider`, регистрация — `AppServiceProvider`. В контейнере (Linux) PSR-4 чувствителен к регистру; сейчас спасает classmap.
- Уже удалено в этапе 1: `ContextBuilder::build`, `ToolCall`.

## Scope

- `app/Context/DiaryPreviewService.php` (перенос из `app/Diary/`)
- `app/Context/Providers/DiaryProvider.php` — только источник лимита
- `app/Diary/DiaryRetrievalService.php` — только константа лимита
- `config/context.php` — ключ лимита записей дневника (если выбран config)
- `app/Http/Controllers/DiaryEntryController.php` — только `use` превью
- `app/Observers/MessageObserver.php` (создать)
- `app/Models/Message.php` — удалить `booted()`, подключить Observer атрибутом `#[ObservedBy]`
- `app/Llm/DeepSeekChatProvider.php` (переименование файла и класса), `app/Providers/AppServiceProvider.php`
- `phpstan-baseline.neon`
- `tests/Feature/DiaryTest.php`, `tests/Feature/MessageTokenEstimateTest.php` (создать)

**Out of scope:** `ContextAssembler` (не трогать, в т. ч. без правок кроме возможного `use` — по итогу не менять); перенос `TokenEstimator` в Llm — отклонено (ADR-001 п. 4). `TokenEstimator` остаётся в `App\Context`; изменение алгоритма retrieval и формы ответа превью; промпты.

## Steps

1. Характеризация: `POST /characters/{id}/diary/preview` — форма ответа и выбранные записи; сохранение сообщения проставляет `token_estimate` и `token_estimator_version`, повторное сохранение без изменения `body` не пересчитывает.
2. Перенести `DiaryPreviewService` в `App\Context`. Лимит истории — из того же источника, что у `DiaryProvider` (без отдельного `config('copilot.history_limit', 15)`), если это не меняет вывод теста шага 1; иначе — остановиться и спросить.
3. Один лимит записей дневника: либо config-ключ, либо публичная константа в одном классе; второе объявление удалить.
4. `MessageObserver::saving(Message $m)` с `TokenEstimator` в конструкторе; логика та же. Подключение — `#[ObservedBy(MessageObserver::class)]` на модели.
5. DeepSeek: переименовать класс в `DeepSeekChatProvider`; файл — `app/Llm/DeepSeekChatProvider.php`. На Windows регистр меняется только двойным переименованием (`git mv a tmp && git mv tmp b` — выполняет пользователь, т. к. `git` запрещён; в отчёте — точные команды). Проверка в контейнере: `docker compose exec laravel.test ls app/Llm`.
6. `sail composer dump-autoload` и тесты.
7. ReembedDiaryCommand: UPDATE character_diary_entries → DiaryEntryRepository::updateContent. 
  После правки grep "UPDATE character_diary_entries" app/ — только в DiaryEntryRepository.
8. ContextAssembler: если diary-секция отбрасывается из-за пустого 
  контента после LineTrimmer, но diary_ids в metadata есть — 
  добавить reason: 'trimmed' или аналогичный, чтобы было понятно.

## Definition of Done

- [ ] `rg -F "use App\Context" app/Diary` → пусто
- [ ] `rg -F "use App\Context" app --glob "!app/Context/**"` → только `app/Observers/MessageObserver.php` и `app/Http/Controllers/DiaryEntryController.php` (превью теперь в Context)
- [ ] `rg "app\(" app --glob "!app/Providers/**"` → пусто
- [ ] `rg "const LIMIT|MAX_ENTRIES" app` → одно объявление (или ни одного, если config)
- [ ] `rg "DeepseekChatProvider" app tests` → пусто; `ls app/Llm` в контейнере показывает `DeepSeekChatProvider.php`
- [ ] `git diff app/Context/ContextAssembler.php` → пусто
- [ ] записи baseline `debt: 029` удалены
- [ ] `sail artisan test` — зелёные; `sail pint --test` — чисто; phpstan — OK

## Report

Формат — `.cursor/rules/task-execution.mdc` §5. Дополнительно: команды переименования для пользователя; выбранный источник лимита.
