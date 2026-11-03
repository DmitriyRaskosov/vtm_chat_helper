# Plan: Вкладки черновиков Copilot

## 1. Frontend (Vue 3)
*   **Компонент `StorytellerCopilotPanel.vue`:**
    *   Удалить блок `v-for` по `drafts` (карточки `.draft-card`).
    *   Добавить панель вкладок (кнопок) по количеству `drafts`. Класс `.draft-tabs`.
    *   Привязать событие `@click` на вкладки к существующему методу `selectDraft(index)`.
    *   Добавить визуальное выделение активной вкладки (класс `.active`), сравнивая `selectedDraftIndex === index`.
    *   Оставить `textarea` для `editedDraft` и кнопку «Отправить в чат».
*   **CSS (`styles.css`):**
    *   Удалить неиспользуемые стили `.draft-list` и `.draft-card`.
    *   Добавить стили для `.draft-tabs` (flex, gap, border-bottom).
    *   Добавить стили для кнопок вкладок (фоновый цвет, цвет текста, активное состояние).
    *   Убедиться, что `textarea` для черновика имеет `max-height: 300px; overflow-y: auto;` (или аналогичное), чтобы оно не растягивало панель.

## 2. Тестирование
*   Автотестов на фронте нет.
*   Ручная проверка: генерация, переключение, редактирование, отправка.
*   Сборка: `docker compose exec frontend npm run build`.