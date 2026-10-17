# 034a - Фабрики моделей

- **Stage:** 2
- **Status:** todo
- **Depends on:** 017 (stub-фабрики созданы), 003 (test-fakes)
- **Risk:** low

## Goal

Заполнить `database/factories/*Factory.php` реальными `definition()`
(14 файлов) и использовать их в тестах вместо прямого `Model::create`.

## Context

- В 017 созданы 14 stub-фабрик с пустым `definition()` — чтобы снять
  28 записей baseline (`class.notFound` + `generics.notSubtype`).
- Сейчас фабрики не используются нигде, кроме `UserFactory`.
- Через месяц это либо удалят (baseline вырастет), либо начнут
  использовать (упадёт, потому что `definition()` пустой).
- Тесты `CharacterTest`, `SceneTest`, `MessageTest`, `DiaryTest`
  создают модели через `Model::create([...])` вручную — дублирование.

## Scope

- `database/factories/*Factory.php` (14 файлов) — заполнить `definition()`
- `tests/Feature/CharacterTest.php`, `SceneTest.php`, `MessageTest.php`,
  `DiaryTest.php` — заменить `Model::create` на `Model::factory()`
- `tests/Support/CreatesDiaryEntries.php`, `CreatesSceneMessages.php` —
  перевести на фабрики, если возможно

## Out of scope

- Новые зависимости не добавлять.
- Не менять логику тестов (проверяемое поведение), только способ
  создания моделей.
- Не удалять фабрики, которые кажутся неиспользуемыми — сначала
  проверить, что они не нужны для будущих задач этапа 2 и 3.

## Steps

1. Пройти по 14 stub-фабрикам, заполнить `definition()` реальными
   полями (см. `database/migrations/` и `app/Models/`).
2. Для каждой фабрики — states (`->archived()`, `->withBiography()` и
   т.п.), если в тестах есть повторяющиеся сценарии.
3. Заменить `Model::create` на `Model::factory()` в тестах поэтапно,
   прогоняя `sail artisan test` после каждой группы.
4. Обновить `CreatesDiaryEntries`, `CreatesSceneMessages` — если
   используют прямое создание моделей.
5. Удалить фабрики, которые после шага 3 остались неиспользуемыми
   и не нужны для этапов 2/3. Обосновать в отчёте.

## Definition of Done

- [ ] Все 14 фабрик имеют непустой `definition()`.
- [ ] `CharacterTest`, `SceneTest`, `MessageTest`, `DiaryTest`
      используют фабрики вместо `Model::create` (или обосновано,
      почему оставлено).
- [ ] `sail pint --test` — OK.
- [ ] `sail phpstan analyse` — OK, baseline: 33 → 33 или меньше.
- [ ] `sail artisan test` — зелёный, счётчик тот же (84) или больше.
- [ ] Если удалена фабрика — обновлён baseline, в отчёте указан рост
      и обоснование.

## Report

- Какие фабрики заполнены, какие удалены.
- Какие тесты переведены на фабрики.
- Размер baseline до/после.
- Если baseline вырос — почему.