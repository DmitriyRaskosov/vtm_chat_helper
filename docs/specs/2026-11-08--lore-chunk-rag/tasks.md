# Tasks: Keyword-triggered RAG по лору

**Status:** Completed

## Этап 1: База данных и нарезка (Backend)
- [x] Создать миграцию `create_canon_lore_chunks_table`.
- [x] Создать модель `CanonLoreChunk`.
- [x] Написать тест для команды нарезки (разбивает большой текст на чанки <= 340 токенов).
- [x] Реализовать `CanonChunkLoreCommand` (нарезка по заголовкам и абзацам).
- [x] Добавить вызов команды в `DatabaseSeeder` (или отдельно, через `canon:chunk-lore`).

## Этап 2: Провайдер (Backend, TDD)
- [x] Написать падающий тест для `LoreRagProvider`:
    - Текст содержит "Катаяны".
    - Провайдер находит статью, делает embedding и возвращает 2 чанка.
- [x] Создать `LoreRagProvider`.
- [x] Реализовать логику поиска сущностей по алиасам.
- [x] Реализовать pgvector-поиск по чанкам.
- [x] Зарегистрировать провайдер в `ContextAssembler` и `config/context.php`.
- [x] Добиться прохождения тестов (Green).

## Этап 3: Финализация (DoD)
- [x] Полный прогон тестов, Pint, PHPStan.
- [x] Проверить, что `ContextAssemblerTest` не сломался (в секции `lore_rag` нет дублей).
- [x] Обновить `spec.md` и `tasks.md` (Status: Completed).