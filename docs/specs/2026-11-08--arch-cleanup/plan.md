# Plan: Оптимизация запросов и унификация API

## 1. Backend (Laravel)
### 1.1 Оптимизация (Кэширование)
*   **User:** В методе `isStorytellerIn(Chronicle $chronicle)` обернуть запрос к БД в `once(fn() => ...)`.
*   **Policies:** Проверить `CharacterPolicy`, `MessagePolicy`, `ScenePolicy`. Все запросы к `chronicle_members` (например, проверка роли) обернуть в `once()`.
*   **CurrentChronicle:** В методе `get()` убрать fallback `find()`. Если `Route::current()->parameter('chronicle')` не является инстансом `App\Models\Chronicle`, бросать `NotFoundException`.

### 1.2 Унификация (404 vs 403)
*   **Исключения:** Создать `App\Chronicle\Exceptions\ChronicleAccessDeniedException` (наследник `NotFoundException`).
*   **Policies:** В `CharacterPolicy::view`, `ScenePolicy::view`, `MessagePolicy::view` и т.д. добавить проверку: если `$model->chronicle_id !== $user->currentChronicleId()`, выбрасывать `ChronicleAccessDeniedException` (404). Если совпадает, но роль не позволяет — возвращать `false` (403).
*   **Роуты:** Убрать `->can()` на маршрутах, где проверка теперь внутри Policy (если нужно).

### 1.3 Тесты
*   Обновить `AccessMatrixTest`: для ролей `player_foreign` и `player_other_chronicle` ожидать 404 вместо 403.
*   Написать тест `CurrentChronicleTest`: передача невалидного ID или отсутствие параметра дает 404.
*   Написать тест `QueryCacheTest`: проверить, что вызов `isStorytellerIn` дважды делает только 1 SQL-запрос (через `DB::listen`).

## 2. Риски
*   **Ломка фронтенда:** Фронтенд может ожидать 403 и показывать «Вам запрещено», а теперь получит 404 (что может вызвать редирект на страницу «Не найдено»). Нужно убедиться, что `apiErrorMessage` корректно обрабатывает 404.
*   **Тесты:** Изменение кодов ответов сломает кучу тестов, которые ожидают 403. Придется пройтись по всем.