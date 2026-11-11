# Plan: UX/UI Полировка интерфейса

**Status:** Completed
**Date:** 2026-11-10

## 1. Подход и архитектура

**Визуальные изменения (CSS):**
Основная работа ведётся в `frontend/src/styles.css`. Добавляются CSS-переменные для цветов и анимаций.
*   **Скроллбары:** `::-webkit-scrollbar` и `scrollbar-width`.
*   **Инпуты/Селекты:** Убираются стандартные `appearance: none`, добавляются кастомные рамки и `box-shadow` при `:focus`.
*   **Анимации:** `@keyframes fadeInUp`, `@keyframes rollDice`.

**Vue-компоненты:**
*   **`ChatLog.vue`:** Внедряется утилита для парсинга текста: ремарки (между тире) оборачиваются в `<span class="action-beat">`, упоминания `@Имя` в `<span class="mention">`. Также добавляется логика отступов по `author_id`.
*   **`ChatComposer.vue`:** Добавляется кнопка сворачивания OOC (использует `useChatPanels.js` или локальный стейт).
*   **`TurnBadge.vue`:** Добавляется CSS-анимация пульсации.
*   **`DiceRoller.vue`:** Внедряется таймаут с CSS-анимацией перед установкой финального результата в стейт.
*   **`AppNav.vue`:** Медиа-запросы для скрытия текста.
*   **`RightSidebar.vue`:** Добавление `v-if="isStoryteller"` для Copilot и Событий, добавление Skeleton-компонентов.
*   **`ChatCharacterDrawer.vue`:** Фильтрация полей (вывод только тех, что можно менять).

**Роутинг и ассеты:**
*   Создание `frontend/src/views/NotFoundView.vue` и добавление catch-all роута в `router.js`.
*   Замена `public/favicon.ico`.

## 2. Затрагиваемые файлы

**Frontend:**
- `frontend/src/styles.css` — глобальные стили, анимации, скроллбары.
- `frontend/src/utils/textParser.js` — **новый файл**: парсинг ремарок и упоминаний.
- `frontend/src/components/chat/ChatLog.vue` — применение парсера и стилей группировки.
- `frontend/src/components/chat/ChatComposer.vue` — кнопка сворачивания OOC.
- `frontend/src/components/chat/TurnBadge.vue` — анимация хода.
- `frontend/src/components/chat/DiceRoller.vue` — анимация броска.
- `frontend/src/components/layout/AppNav.vue` — адаптивность.
- `frontend/src/components/chat/RightSidebar.vue` — скрытие Copilot, skeletons.
- `frontend/src/components/chat/ChatCharacterDrawer.vue` — фильтрация полей.
- `frontend/src/views/ChatView.vue` — skeletons при загрузке.
- `frontend/src/views/NotFoundView.vue` — **новый файл**.
- `frontend/src/router.js` — роут 404.
- `frontend/public/favicon.ico` — замена.
- `frontend/index.html` — обновление ссылки на favicon (если нужно).

**Tests:**
- `frontend/src/utils/textParser.test.js` — тесты на парсер (ремарки, упоминания).

## 3. Верификация AC

| AC | Как проверяется |
|---|---|
| AC-1, AC-6 | `textParser.test.js` (проверка разметки на выходе). |
| AC-2, AC-3 | Вручную (DevTools: проверка классов и keyframes). |
| AC-4, AC-12 | Вручную (визуальный осмотр). |
| AC-5 | Вручную (клик по кнопке). |
| AC-7 | Вручную (передача хода). |
| AC-8 | Вручную (бросок куба). |
| AC-9 | Вручную (сужение окна браузера). |
| AC-10 | Вручную (вход под игроком). |
| AC-11 | Вручную (открытие чарлиста в чате). |
| AC-13 | Вручную (перезагрузка страницы, отслеживание загрузки). |

## 4. Риски

*   **Риск:** Тяжелая логика парсинга в `ChatLog.vue` может замедлить рендер длинных чатов.
    **Митигация:** Использовать `computed` свойства, минимизировать регулярные выражения.
*   **Риск:** CSS-анимации могут конфликтовать с существующей вёрсткой (например, слайдеры «поедут»).
    **Митигация:** Тестировать в обеих темах (`theme-gothic`, `theme-blood`), использовать `transform` вместо `margin/top`.
*   **Риск:** `appearance: none` для селектов может сломать доступность (accessibility) на мобильных.
    **Митигация:** Оставить нативные селекты на мобильных через медиа-запросы, кастомизировать только на десктопе.

## 5. Допущения

*   [Assumption] Иконки для меню уже есть в проекте (используем существующие).
*   [Assumption] Favicon можно сгенерировать/взять из ресурсов проекта.