# Plan: Макросы кубиков (Dice Macros)

## 1. Backend (Laravel)

### 1.1 База данных и Модели
*   **Миграция:** Создать таблицу `dice_macros`:
    *   `id`, `user_id` (FK на users, cascade), `name` (string), `pool` (unsignedSmallInteger), `difficulty` (unsignedSmallInteger), `dice_type` (unsignedSmallInteger, default 10), `use_willpower` (boolean, default false), `stats` (jsonb, nullable), `timestamps`.
*   **Модель:** Создать `DiceMacro`. Casts: `stats => array`, `use_willpower => boolean`.

### 1.2 API и Права
*   Создать `DiceMacroController` (CRUD):
    *   `GET /api/dice-macros` (только свои).
    *   `POST /api/dice-macros`.
    *   `PATCH /api/dice-macros/{macro}` (только свои).
    *   `DELETE /api/dice-macros/{macro}` (только свои).
*   Использовать Policy или `abort_if($macro->user_id !== Auth::id())` для защиты.
*   Создать `DiceMacroResource`.

### 1.3 Тестирование (Backend)
*   `DiceMacroApiTest`: создание, чтение (только своих), обновление, удаление, защита от редактирования чужих макросов.

## 2. Frontend (Vue 3)

### 2.1 Личный Кабинет (ProfileView.vue)
*   Добавить блок «Макросы кубиков».
*   Список существующих макросов с кнопками «Редактировать» и «Удалить».
*   Кнопка «Создать макрос» -> открывает модалку `MacroModal.vue`.
*   `MacroModal.vue`: поля Название, Пул, Сложность, Тип куба, Трата Воли (чекбокс), Статы (строка через запятую, сохраняется как массив).
*   Composable `useDiceMacros.js` для управления CRUD.

### 2.2 Окно броска (DiceRoller.vue)
*   Загружать список макросов текущего пользователя при открытии.
*   Добавить селект «Выбрать макрос» над полями.
*   При выборе макроса: заполнять `pool`, `difficulty`, `diceType`, `useWillpower`.
*   Пользователь может менять значения после выбора (это просто автозаполнение).
*   Composable `useDiceMacros.js` использовать для загрузки списка.