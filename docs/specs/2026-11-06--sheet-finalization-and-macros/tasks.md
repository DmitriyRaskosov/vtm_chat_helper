# Tasks: Финализация Чарлиста и Связка с Макросами

**Status:** Completed

## Этап 1: Backend (Удаление CharacterState)
- [x] Удалить `CharacterStateController`, `CharacterStateService`, `CharacterStateResource`.
- [x] Удалить маршрут `PUT /api/characters/{character}/state` из `routes/api.php`.
- [x] Обновить `CharacterCreationService` (убрать создание CharacterState).
- [x] Обновить `CharacterStateProvider` (убедиться, что он не зависит от удаленных классов).
- [x] Переписать/удалить старые тесты (`CharacterStateTest`).
- [x] Прогнать `php artisan test`.

## Этап 2: Frontend (Панель в чате)
- [x] В `ChatCharacterDrawer.vue` заменить `SheetStateSection` на новый UI (точки для крови/воли, здоровье).
- [x] Настроить `PATCH /stats` при изменении статов в чате.
- [x] Убедиться, что изменения в чате видны на странице листа персонажа (и наоборот).

## Этап 3: Frontend (Связка Макросов)
- [x] В `DiceRoller.vue` пробросить данные о статах текущего персонажа.
- [x] Написать логику `calculatePool(macroStats, characterStats)`.
- [x] При выборе макроса подставлять вычисленный пул в инпут `pool`.
- [x] Проверить, что если стат не выбран, пул считается корректно (как 0).

## Этап 4: Финализация (DoD)
- [x] Полный прогон тестов, Pint, PHPStan, сборка фронта.
- [x] Обновить `spec.md` и `tasks.md` (Status: Completed, Post-Implementation Notes).