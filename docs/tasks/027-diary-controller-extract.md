# 027 — DiaryEntryController: правка и пересоздание в модуле Diary, VectorLiteral

- **Stage:** 2
- **Part:** 2
- **Status:** todo
- **Depends on:** 026a
- **Risk:** med
- **Разрешённые зависимости:** нет

## Goal

`DiaryEntryController` только вызывает сервисы Diary и отдаёт ответ, эмбеддинг считается вне транзакции, а вектор форматирует один класс `App\Rag\VectorLiteral`.

## Context

- Остаток задачи 017: в конструктор перенесён только `EmbeddingProvider`; `DiaryPreviewService`, `DiaryWriterService`, `DiarySummarizerService` внедряются в параметры `preview`/`regenerate`.
- `update()`: `abort(422, 'Entry cannot be empty.')`, затем `DB::transaction` с `$diaryEntry->update(...)`, **вызовом `embed()` внутри транзакции** и сырым `DB::statement('UPDATE ... embedding = ?::vector')`.
- `regenerate()`: `match` по уровню → `abort(422, 'Не удалось пересоздать запись. Проверьте источник.')` при `false`.
- `formatVector` повторён 5 раз: `DiaryWriterService`, `DiarySummarizerService`, `DiaryRetrievalService`, `DiaryEntryController`, `ReembedDiaryCommand`.
- Baseline: `DiaryEntryController` (`$title` на `Model`, `$value` on string) — `debt: 027`.
- `.cursor/rules/llm-context.mdc` §Дневник: внешние вызовы — вне транзакции.

## Scope

- `app/Rag/VectorLiteral.php` (создать; `final`, статический `from(array $vector): string` или экземпляр — обосновать)
- `app/Diary/DiaryEntryEditor.php` (создать): `update(CharacterDiaryEntry, string): CharacterDiaryEntry`, `regenerate(CharacterDiaryEntry): CharacterDiaryEntry`
- `app/Diary/Exceptions/*`
- `app/Diary/{DiaryWriterService,DiarySummarizerService,DiaryRetrievalService}.php` — только замена `formatVector`
- `app/Console/Commands/Diary/ReembedDiaryCommand.php` — только замена `formatVector`
- `app/Http/Controllers/DiaryEntryController.php`
- `phpstan-baseline.neon`
- `tests/Feature/DiaryTest.php`, `tests/Unit/VectorLiteralTest.php` (создать)

**Out of scope:** логика генерации текста Writer/Summarizer; persistence L0/L1 (028); сериализация (022); перенос `DiaryPreviewService` (029).

## Steps

1. Характеризация: `PATCH /diary-entries/{id}` с пустым текстом → 422 `Entry cannot be empty.`; с текстом → текст и эмбеддинг обновлены (вектор из `FakeEmbeddingProvider`); `POST .../regenerate` для L0, L1 и при неудаче → 422 с текущим текстом.
2. `VectorLiteral` + unit-тест (формат `[0.1,0.2,...]`, тот же, что у текущего `formatVector`). Заменить 5 копий.
3. `DiaryEntryEditor::update`: валидация пустого текста → доменное исключение (422, текст тот же); **сначала `embed()`, потом** одна транзакция: текст + вектор.
4. `regenerate`: выбор по уровню и исключение при неудаче — в сервисе.
5. Контроллер: все зависимости в конструктор, без `DB::` и `abort`.

## Definition of Done

- [ ] `rg "function formatVector" app` → пусто
- [ ] `rg "DB::|abort" app/Http/Controllers/DiaryEntryController.php` → пусто
- [ ] в `DiaryEntryEditor::update` вызов `embed()` стоит вне `DB::transaction` (проверить чтением; в отчёте — фрагмент)
- [ ] у action-методов контроллера нет параметров-сервисов (только request и модели)
- [ ] записи baseline `debt: 027` удалены
- [ ] `sail artisan test` — зелёные; `sail pint --test` — чисто; phpstan — OK

## Report

Формат — `.cursor/rules/task-execution.mdc` §5.
