# Tasks: Поддержка нескольких персонажей (Multi-character)

Status: Completed

## Этап 1: Backend (База и Модели)
- [x] Создать миграцию `remove_unique_active_player_index_from_characters`.
- [x] Создать миграцию `add_deleted_at_to_characters_table`.
- [x] В модели `Character` подключить трейт `SoftDeletes`.
- [x] Обновить `ProfileCharacterResource`, добавив флаг `is_active`.

## Этап 2: Backend (TDD - Логика и API)
- [x] Написать падающий тест `MultiCharacterTest`: 
  - игрок создает второго PC (успех);
  - игрок архивирует PC;
  - игрок мягко удаляет PC (проверить, что `deleted_at` заполнен);
  - удаленный PC не возвращается в `GET /api/profile/characters`.
- [x] Обновить `CharacterPolicy`: разрешить владельцу `archive`, `restore`, `delete`.
- [x] В `ProfileController::index` убедиться, что возвращаются и активные, и архивные (но не удаленные).
- [x] В `CharacterSheetController` добавить метод `destroy()` (вызывает `$character->delete()`).
- [x] Добавить маршрут `DELETE /api/characters/{character}`.
- [x] Добиться прохождения тестов (Green).

## Этап 3: Frontend (Логика и Интерфейс)
- [x] В `useProfile.js` добавить методы `archiveCharacter(id)` и `deleteCharacter(id)`.
- [x] В `ProfileView.vue`:
  - Добавить чекбокс/таб «Показывать архивных».
  - На карточке активного персонажа добавить кнопку «В архив».
  - На карточке архивного — кнопки «Восстановить» и «Удалить навсегда» (с подтверждением).
- [x] В `ChatComposer.vue`:
  - Добавить селектор «Писать от лица» для игроков с >1 активным PC.
  - Синхронизировать выбор с `localStorage` (`chat-selected-pc-id`).
  - При отправке сообщения использовать `character_id` из селектора.
  - Скрыть архивных персонажей из селектора в чате.

## Этап 4: Финализация (DoD)
- [x] Прогнать полный набор тестов: `docker compose exec laravel.test php artisan test`.
- [x] Прогнать `pint` и `phpstan`.
- [x] Сборка фронта: `cd frontend && npm run build`.
- [x] Сверить с Acceptance Criteria из `spec.md`.
- [x] Поставить `Status: Completed` в `spec.md` и `tasks.md`.
- [x] Добавить в `spec.md` раздел "Post-Implementation Notes".