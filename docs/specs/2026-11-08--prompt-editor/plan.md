# Plan: UI для редактирования промптов

## 1. Backend (Laravel)
### 1.1 База данных и Модели
*   Миграция: `prompt_overrides` (key_string PK, content_text).
*   Модель: `PromptOverride`.

### 1.2 Метаданные
*   Создать `config/prompts_metadata.php`: 
    *   Массив с ключами промптов.
    *   Значения: `title`, `description`, `variables`, `group`.

### 1.3 Repository и API
*   `PromptRepository::get($key)`: проверка БД -> фолбэк на файл.
*   API: GET `/api/workshop/prompts`, PUT `/api/workshop/prompts/{key}`, DELETE `/api/workshop/prompts/{key}`.

## 2. Frontend (Vue 3)
### 2.1 UI (Workshop)
*   В `WorkshopView.vue` добавить вкладку «Промпты».
*   Создать `PromptEditor.vue` (левая колонка: список, правая: textarea + подсказки).
*   **Карта контекста:** Создать компонент `ContextMapModal.vue`. 
    *   Внутри — статический массив шагов для каждого прохода (Copilot Topics, Copilot Reply, Diary Writer).
    *   В правой колонке редактора добавить кнопку вызова этой модалки.
    *   Данные для карты можно захардкодить на фронте (это статическая документация), либо отдавать из `config/context.php` через API. Проще захардкодить в `ContextMapModal.vue`.

## 3. Риски
*   Предупреждать, если мастер сломает JSON-структуру (флаг `is_json_required`).