# Tasks: Боевая Секвенция (Этап 2)

**Status:** Completed
**Date:** 2026-11-09

## Stage 1: Composables и API
- [x] Создать `frontend/src/composables/useCombatTracker.js`.
- [x] Реализовать получение `GET /scenes/{scene}/combat` (с поллингом для игроков).
- [x] Реализовать методы: `nextTurn`, `setActive`, `reveal`, `setInitiative`, `addParticipant`, `startCombat`.

## Stage 2: UI Компоненты
- [x] Создать `CombatParticipantCard.vue` (аватар, имя, бейдж).
- [x] Добавить обработку Drag-and-Drop в карточку (draggable, emits).
- [x] Создать `CombatTracker.vue` (контейнер, сортировка, Drag-over).
- [x] Реализовать заглушку для скрытых игроков (вопрос).
- [x] Добавить меню управления (кнопки «Следующий», «Добавить», «Завершить»).

## Stage 3: Интеграция
- [x] Встроить `CombatTracker.vue` в интерфейс чата (плавающее окно).
- [x] Связать кнопку «Начать бой» с composable.

## Stage 4: Finalization
- [x] Ручная проверка сценария (создание, добавление, ходы, скрытие, D&D).
- [x] `npm run build` (зеленый).
- [x] Обновить `spec.md, plan.md, tasks.md` (Status: Completed).