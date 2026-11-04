# Tasks: Макросы кубиков (Dice Macros)

## Этап 1: Backend (TDD - База и API)
- [x] Создать миграцию `create_dice_macros_table`.
- [x] Создать модель `DiceMacro`.
- [x] Написать падающий тест `DiceMacroApiTest` (CRUD, защита чужих макросов).
- [x] Создать `DiceMacroController`, `StoreDiceMacroRequest`, `UpdateDiceMacroRequest`.
- [x] Создать `DiceMacroResource`.
- [x] Добавить маршруты.
- [x] Добиться прохождения тестов (Green).

## Этап 2: Frontend (ЛК)
- [x] Создать `useDiceMacros.js`.
- [x] Создать компонент `MacroModal.vue` (поля: Название, Пул, Сложность, Тип, Воля, Статы через запятую).
- [x] В `ProfileView.vue` добавить секцию «Макросы кубиков» (список + кнопки).

## Этап 3: Frontend (DiceRoller)
- [x] В `DiceRoller.vue` загрузить макросы пользователя.
- [x] Добавить селект «Выбрать макрос».
- [x] Настроить автозаполнение полей при выборе макроса.
- [x] Сборка фронта в Docker (`docker compose exec frontend npm run build`).

## Этап 4: Финализация (DoD)
- [x] Прогон полного набора тестов (`php artisan test`).
- [x] Pint, PHPStan.
- [x] Обновить `spec.md` и `tasks.md` (Status: Completed, Post-Implementation Notes).