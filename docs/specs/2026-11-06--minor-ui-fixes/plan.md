# Plan: Minor UI Fixes

## 1. Backend (Laravel)
*   **MessageResource:** Добавить поле `avatar_path` (подтягивать из `authorCharacter->avatar_path`).
*   **ProfileApiTest:** Убедиться, что тест не ждет 403 для рассказчика.

## 2. Frontend (Vue 3)
### 2.1 UI
*   `AppNav.vue`: Заменить `📖` на `💬`.
*   `ProfileView.vue`: Убрать проверку `isStoryteller` и заглушку «У рассказчика нет ЛК».
*   `ChatLog.vue`: 
    *   Добавить `<img>` с аватаром (используя `message.avatar_path`). Если аватара нет, показывать заглушку или ничего.
    *   Проверить `white-space: pre-wrap` на `.msg-body`.
    *   Добавить логику `isScrolledUp` для отображения кнопки «↓» внизу.
*   `styles.css`: Убедиться, что `.msg-actions` имеют `opacity: 0` и `opacity: 1` при `:hover` на `.msg`.

## 3. Риски
*   Аватары могут сломать верстку, если они слишком большие. Нужно жестко задать `width` и `height` (например, 32x32) и `object-fit: cover`.