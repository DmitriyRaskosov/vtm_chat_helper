# Tasks: Оптимизация запросов и унификация API

**Status:** Completed

## Этап 1: Оптимизация (Кэширование)
- [x] Обновить `User::isStorytellerIn()` (обернуть в `once()`).
- [x] Обновить Policies (обернуть запросы к `chronicle_members` в `once()`).
- [x] Написать тест `QueryCacheTest` (проверить количество запросов).
- [x] Убрать fallback в `CurrentChronicle::get()`.
- [x] Написать тест для `CurrentChronicle` (404 без модели).

## Этап 2: Унификация кодов (404 vs 403)
- [x] Создать `ChronicleAccessDeniedException` (404).
- [x] Внедрить проверку хронику в начало методов `view`/`show` во всех Policies. Если чужая — 404.
- [x] Обновить `AccessMatrixTest` (заменить 403 на 404 для чужих хроник).
- [x] Прогнать полный набор тестов и починить те, что упали из-за смены кода.

## Этап 3: Финализация (DoD)
- [x] Прогон полного набора тестов, Pint, PHPStan.
- [x] Сборка фронта.
- [x] Обновить `spec.md` и `tasks.md` (Status: Completed).
