# 003 — Фейки LLM и эмбеддингов, общие хелперы тестов

- **Stage:** 1
- **Status:** done
- **Depends on:** 001
- **Risk:** low
- **Разрешённые зависимости:** нет

## Goal

Тесты подменяют `ChatProvider` и `EmbeddingProvider` фейками в контейнере, общий хелпер дневника живёт в одном месте, unit-проверки парсера лежат в `tests/Unit`.

## Context

- Аудит §2.6: подмена LLM через `Http::fake` по URL DeepSeek; `createDiaryEntry` продублирован в `DiaryTest` и `MessageEditTest`; unit-проверки `DiaryMetadataParser` лежат в Feature; `tests/Unit/ExampleTest.php` пустой.
- Интерфейсы: `App\Llm\ChatProvider`, `App\Rag\EmbeddingProvider`; выбор драйвера — `AppServiceProvider`.
- `.cursor/rules/testing.mdc`.

## Scope

- `tests/Support/FakeChatProvider.php` (создать)
- `tests/Support/FakeEmbeddingProvider.php` (создать)
- `tests/Support/CreatesDiaryEntries.php` (создать, trait)
- `tests/TestCase.php`
- `tests/Feature/*.php` — замена `Http::fake` на фейки и удаление дублей хелпера
- `tests/Unit/DiaryMetadataParserTest.php` (создать — перенос из Feature)
- `tests/Unit/ExampleTest.php` (удалить)

**Out of scope:**
- `app/` целиком (если фейк не встаёт без правки `app/` — остановиться и спросить);
- новые тестовые сценарии (это задача 004).

## Steps

1. Прочитать `ChatProvider` и `EmbeddingProvider`. Реализовать фейки:
   - `FakeChatProvider`: очередь заранее заданных ответов (`push(string|array)`), запись полученных запросов (`requests()`), исключение при пустой очереди.
   - `FakeEmbeddingProvider`: детерминированный вектор длины 1024 из текста (например, по хешу), запись вызовов.
2. В `TestCase` — хелперы `fakeChat(): FakeChatProvider` и `fakeEmbeddings(): FakeEmbeddingProvider`, регистрирующие фейк через `$this->app->instance(...)`.
3. Перевести все `Http::fake` по URL DeepSeek/Ollama в Feature-тестах на фейки. Ассерты на содержимое запроса переписать на `requests()`.
4. Вынести `createDiaryEntry` в trait `CreatesDiaryEntries`, подключить в `DiaryTest` и `MessageEditTest`, удалить копии.
5. Перенести тесты, проверяющие только `DiaryMetadataParser` (без БД и HTTP), в `tests/Unit/DiaryMetadataParserTest.php` (`extends PHPUnit\Framework\TestCase`).
6. Удалить `tests/Unit/ExampleTest.php`.

## Definition of Done

- [ ] `rg "Http::fake" tests` → пусто (или только тесты самих `DeepseekChatProvider`/Ollama-провайдера, если такие есть — перечислить в отчёте)
- [ ] `rg "function createDiaryEntry" tests` → ровно одно вхождение, в `tests/Support`
- [ ] `tests/Unit/ExampleTest.php` удалён
- [ ] число тестов: прежнее − 1 (ExampleTest); ни один тест не удалён без переноса
- [ ] `sail artisan test` — зелёные; `sail pint --test` — чисто; phpstan — без новых ошибок
- [ ] тесты проходят с выключенным Ollama на хосте

## Report

Формат — `.cursor/rules/task-execution.mdc` §5. Дополнительно:
- публичный API фейков (сигнатуры методов) — его впишут в `testing.mdc`.
