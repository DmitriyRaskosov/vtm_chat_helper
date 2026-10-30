# Plan: {Название фичи}

## 1. Backend (Laravel)
*   **Миграции:** Создать миграцию `add_avatar_path_to_characters_table`.
*   **Модели:** Добавить `avatar_path` в `$fillable` и `casts` модели `Character`.
*   **Сервисы:** Создать `CharacterAvatarService` для обработки загрузки (сохранение, удаление старого, ресайз).
*   **API:**
    *   `POST /api/characters/{character}/avatar` — загрузка.
    *   `DELETE /api/characters/{character}/avatar` — удаление.
*   **Policies:** Изменить `CharacterPolicy::update`, чтобы разрешить загрузку только владельцу PC и рассказчику.

## 2. Frontend (Vue 3)
*   **Composables:** Создать `useCharacterAvatar.js` (загрузка, удаление, кэширование blob-url).
*   **Components:** 
    *   Создать `AvatarUploader.vue` (в Мастерской).
    *   Обновить `RightSidebar.vue` для отображения аватарок в списке участников.

## 3. Риски и ограничения
*   **Риск:** Файлы могут занимать много места. *Решение:* Добавить команду `avatar:prune` в будущем.
*   **Зависимости:** Требуется расширение GD (мы его недавно починили в Docker).