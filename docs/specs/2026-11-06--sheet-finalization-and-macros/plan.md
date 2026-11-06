# Plan: Финализация Чарлиста и Связка с Макросами

## 1. Backend (Laravel)
### 1.1 Удаление старой системы
*   Удалить `CharacterState` модель, миграцию (или оставить таблицу как legacy), `CharacterStateController`, `CharacterStateService`, `CharacterStateResource`.
*   Удалить маршрут `PUT /api/characters/{character}/state`.
*   Обновить `CharacterCreationService` (удалить создание `CharacterState`).
*   Переписать тесты, которые использовали старый API.

### 1.2 Context (Copilot)
*   Убедиться, что `CharacterStateProvider` больше не ссылается на удаленный сервис, а читает только `characters.stats` (кровь и здоровье).

## 2. Frontend (Vue 3)
### 2.1 Панель в чате (ChatCharacterDrawer)
*   Удалить старый `SheetStateSection.vue`.
*   Встроить в Drawer компонент `StatDots.vue` (или облегченную версию `CharacterSheetGrid`), чтобы отображать кровь, здоровье и волю.
*   Настроить сохранение через `PATCH /api/characters/{character}/stats`.

### 2.2 Связка Макросов и Чарлиста (DiceRoller)
*   При открытии `DiceRoller.vue` передавать туда объект `character` (или его `stats`), от лица которого идет бросок.
*   При выборе макроса (если у него заполнено поле `stats`):
    *   Пройтись по массиву `stats` из макроса.
    *   Для каждого стата посчитать количество точек со значением `1` (постоянные) и `2` (временные) в `character.stats`.
    *   Сложить их.
    *   Подставить сумму в `pool`.
*   Если стат не найден в `character.stats`, считать его за 0.

## 3. Риски
*   Не забыть удалить старые интерфейсы и тесты, иначе проект будет тащить мертвый код.