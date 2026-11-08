# Tasks: UI для редактирования промптов

**Status:** Completed

## Этап 1: Backend (БД и Repository)
- [x] Создать миграцию `create_prompt_overrides_table`.
- [x] Создать модель `PromptOverride`.
- [x] Создать `config/prompts_metadata.php`.
- [x] Обновить `PromptRepository::get()`.
- [x] Написать тест `PromptRepositoryTest`.

## Этап 2: Backend (API)
- [x] Создать `PromptController` (index, update, destroy).
- [x] Написать Feature-тест API.

## Этап 3: Frontend (UI)
- [x] Создать `usePrompts.js`.
- [x] Создать `PromptEditor.vue`.
- [x] Внедрить вкладку в `WorkshopView.vue`.
- [x] Создать `ContextMapModal.vue` (статический список секций).
- [x] Добавить кнопку «Карта контекста» над редактором.

## Этап 4: Финализация (DoD)
- [x] Полный прогон тестов, Pint, PHPStan, сборка.
- [x] Ручная проверка (изменить промпт, сбросить, посмотреть карту).
- [x] Обновить `spec.md` и `tasks.md` (Status: Completed).
