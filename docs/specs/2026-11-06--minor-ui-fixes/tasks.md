# Tasks: Minor UI Fixes

**Status:** Completed

## Этап 1: Backend (API)
- [x] Добавить `avatar_path` в `MessageResource`.
- [x] Обновить `ChatJsonShapeTest`, чтобы он проверял `avatar_path`.
- [x] Прогнать тесты (Green).

## Этап 2: Frontend (UI)
- [x] Заменить иконку чата в `AppNav.vue`.
- [x] Убрать заглушку в `ProfileView.vue`.
- [x] Добавить аватары в `ChatLog.vue` (с жесткими стилями: 32x32, border-radius).
- [x] Проверить/добавить переносы строк (`.msg-body`).

## Этап 3: Frontend (UX и Скролл)
- [x] Добавить кнопку «↓» (вниз) в `ChatLog.vue` для IC и OOC.
- [x] Убедиться, что кнопки действий (`msg-actions`) показываются только при наведении (hover).

## Этап 4: Финализация (DoD)
- [x] Полный прогон тестов, Pint, PHPStan, сборка фронта.
- [ ] Ручная проверка (аватары видны, скролл работает).
- [x] Обновить `spec.md` и `tasks.md` (Status: Completed).