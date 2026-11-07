# Plan: Поддержка нескольких персонажей (Multi-character)

## 1. Backend (Laravel)

### 1.1 База данных и Модели
*   **Миграция 1:** Удалить частичный уникальный индекс `characters_one_active_player_per_chronicle`.
*   **Миграция 2:** Добавить колонку `deleted_at` (timestamp, nullable) в таблицу `characters`.
*   **Модель `Character`:** Подключить трейт `SoftDeletes`.
*   *Примечание:* Мы используем Soft Deletes вместо физического удаления, так как триггер `prevent_world_entity_delete` в Postgres блокирует физическое удаление `world_entities`.

### 1.2 Авторизация (Policies)
*   В `CharacterPolicy` разрешить владельцу (`user_id === Auth::id()`):
    *   `archive` (уже есть, но проверить права).
    *   `restore` (возврат из архива).
    *   `delete` (мягкое удаление).

### 1.3 API
*   `GET /api/profile/characters`: Изменить запрос, чтобы он возвращал и активных, и архивных (`is_active = false`), но **исключал** удаленных (`withTrashed` не используем). Добавить флаг `is_active` в `ProfileCharacterResource`.
*   `DELETE /api/characters/{character}`: Новый метод `destroy()` в `CharacterSheetController`. Вызывает `$character->delete()`.
*   `POST /api/characters/{character}/archive`: Уже существует, просто обновить Policy.
*   `POST /api/characters/{character}/restore`: Уже существует, просто обновить Policy.

## 2. Frontend (Vue 3)

### 2.1 Composables
*   `useProfile.js`:
    *   Обновить `loadCharacters()`, чтобы сохранять флаг `is_active`.
    *   Добавить метод `archiveCharacter(id)`.
    *   Добавить метод `deleteCharacter(id)`.
*   `useChatMessages.js` (или `ChatView.vue`):
    *   Добавить логику выбора `selectedPcId` из `localStorage` (ключ `chat-selected-pc-id`).
    *   Валидация: если сохраненный ID не принадлежит игроку, сбрасывать на первого доступного.

### 2.2 Компоненты (UI)
*   **ProfileView.vue:**
    *   Добавить чекбокс/таб «Показывать архивных» (фильтрация списка).
    *   На карточке персонажа добавить кнопки: «В архив» (если активен) и «Удалить навсегда» (с модалкой подтверждения).
*   **ChatComposer.vue:**
    *   Если `isStoryteller === false` и у игрока > 1 PC, показать выпадающий список: 
        `[Писать от лица: Мой Вампир ▼]`.
    *   При смене значения обновлять `selectedPcId` в `localStorage` и в `useChatMessages`.
    *   При отправке сообщения отправлять выбранный `character_id`.

## 3. Риски и ограничения
*   **Soft Deletes:** Убедиться, что глобальные запросы (например, `SceneProvider` или `DiaryWriterService`) не пытаются получить данные удаленного персонажа (SoftDeletes отсекает их автоматически на уровне Eloquent, но нужно проверить сырые SQL).
*   **Архивные персонажи в чате:** Если персонаж в архиве, может ли игрок писать от его лица? (Правильно было бы скрыть архивных из селектора в чате).
*   **Вынесено из критериев приёмки:** снят частичный уникальный индекс `characters_one_active_player_per_chronicle`. Архив — `is_active = false`. Удаление мягкое (`deleted_at`): триггер не даёт стереть `world_entities`. Селектор пишет `localStorage` ключ `chat-selected-pc-id` и отправляет `character_id`; бэкенд проверяет, что PC принадлежит игроку.