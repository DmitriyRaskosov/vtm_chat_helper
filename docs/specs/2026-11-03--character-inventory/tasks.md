# Tasks: Инвентарь персонажа

**Status:** Completed

## Этап 1: Backend (База и Модели)
- [x] Создать миграцию `create_character_items_table`.
- [x] Создать модель `CharacterItem` (fillable, casts: is_equipped => boolean, quantity => integer).
- [x] Добавить связь `items()` в модель `Character`.

## Этап 2: Backend (TDD - API и Права)
- [x] Написать падающий тест `CharacterItemTest` (игрок не может менять чужой инвентарь, может менять свой).
- [x] Обновить `CharacterPolicy` (добавить метод `manageItems`).
- [x] Создать `CharacterItemController` с методами index, store, update, destroy.
- [x] Добавить маршруты в `routes/api.php`.
- [x] Добиться прохождения тестов (Green).

## Этап 3: Context (Промпт)
- [x] Написать падающий тест `InventoryProviderTest` (проверить, что провайдер собирает правильный текст).
- [x] Создать `InventoryProvider` (разделять на Equipped и Carried).
- [x] Зарегистрировать его в `ContextAssembler` (лимит 200 токенов).
- [x] Добавить правило в `resources/prompts/copilot/reply.system.md`.
- [x] Добиться прохождения тестов (Green).

## Этап 4: Frontend (UI и Интерактив)
- [x] Создать `useCharacterInventory.js`.
- [x] Создать `InventoryPanel.vue` (два списка, форма добавления, перетаскивание).
- [x] В `ChatComposer.vue` добавить кнопку `🎒`.
- [x] В `ChatView.vue` подключить панель к `useChatPanels`.
- [x] Проверить сборку фронта в Docker.

## Этап 5: Финализация (DoD)
- [x] Прогнать полный набор тестов (`php artisan test`).
- [x] Прогнать `pint` и `phpstan`.
- [x] Сборка фронта в Docker (`docker compose exec frontend npm run build`).
- [x] Обновить `spec.md` и `tasks.md` (Status: Completed, Post-Implementation Notes).