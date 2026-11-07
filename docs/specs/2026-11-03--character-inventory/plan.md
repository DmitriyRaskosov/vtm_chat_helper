# Plan: Инвентарь персонажа

## 1. Backend (Laravel)

### 1.1 База данных и Модели
*   **Миграция:** Создать `create_character_items_table`:
    *   `id`, `character_id` (FK cascade), `name` (string), `description` (text, nullable), `quantity` (integer, default 1), `is_equipped` (boolean, default false), `timestamps`.
*   **Модель:** Создать `CharacterItem`. Добавить связь `items()` в модель `Character`.

### 1.2 API и Права
*   Создать `CharacterItemController` (CRUD):
    *   `GET /api/characters/{character}/items`
    *   `POST /api/characters/{character}/items`
    *   `PATCH /api/characters/{character}/items/{item}`
    *   `DELETE /api/characters/{character}/items/{item}`
*   В `CharacterPolicy` добавить метод `manageItems(User, Character)`: разрешить владельцу PC и рассказчику хроники.

### 1.3 Context Engineering (LLM)
*   Создать `app/Context/Providers/InventoryProvider.php`:
    *   Возвращает секцию `inventory`.
    *   Формат: `Equipped: [список] \n Carried: [список]`.
    *   Если инвентарь пуст — `omitted`.
*   Зарегистрировать `InventoryProvider` в `ContextAssembler` (с лимитом токенов, например, 200).
*   В промпт `resources/prompts/copilot/reply.system.md` добавить правило: *"Секция Equipped — это часть внешности. Не считай эти предметы за дополнительные. Не выдумывай предметы, которых нет в Carried."*

## 2. Frontend (Vue 3)

### 2.1 Логика и UI
*   Создать composable `useCharacterInventory.js` (загрузка, добавление, изменение, удаление вещей).
*   Создать компонент `InventoryPanel.vue` (плавающее окно, как Заметки):
    *   Два списка: «Экипировано» (галочка `is_equipped = true`) и «В рюкзаке».
    *   Форма быстрого добавления (Название, Кол-во).
*   Добавить иконку `🎒 Инвентарь` в тулбар `ChatComposer.vue`.
*   В `ChatView.vue` отрендерить `InventoryPanel` (используя `useChatPanels` и `useDraggable` для перетаскивания).

## 3. Тестирование (TDD)
*   `CharacterItemTest`: тесты на права (игрок может менять своего, чужой/рассказчик — как положено).
*   `InventoryProviderTest`: проверить, что `Equipped` и `Carried` корректно формируют строку для промпта и укладываются в лимиты.

## 4. Риски
*   Если помощник получит и надетое, и рюкзак без пометки, одна вещь прочитается дважды.
*   **Вынесено из критериев приёмки:** таблица `character_items` (id, character_id, name, quantity, description, is_equipped). CRUD `/api/characters/{id}/items`. Право `manageItems` — владелец PC и рассказчик. Секция контекста — `InventoryProvider` (лимит токенов, надетое помечено как часть внешности). По итогам рюкзак в промпт не попадает: см. Post-Implementation Notes спеки.