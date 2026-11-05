# Plan: Игровое время (In-Game Time)

## 1. Backend (Laravel)

### 1.1 База данных и Модели
*   **Миграция 1:** В таблицу `chronicles` добавить колонку `current_in_game_date` (datetime, default now()).
*   **Миграция 2:** В таблицу `scenes` добавить колонку `started_at_in_game` (datetime, nullable).
*   **Миграция 3:** В таблицу `character_diary_entries` добавить колонку `in_game_date` (datetime, nullable).
*   **Модели:** Добавить `current_in_game_date` в `$casts` (как `datetime`), обновить `$fillable`. Аналогично для сцен и дневников.

### 1.2 API и Логика
*   **API:** `PATCH /api/chronicles/{chronicle}/time` (доступно только storyteller).
    *   Принимает `datetime`. Обновляет `current_in_game_date` в хронике.
    *   Если есть активная сцена, обновляет и её `started_at_in_game`.
*   **При создании сцены (`SceneLifecycleService`):** копировать `current_in_game_date` из хроники в `started_at_in_game` новой сцены.
*   **При создании записи дневника (`DiaryWriterService`, `DiarySummarizerService`):** записывать `in_game_date` = текущее время хроники.

### 1.3 LLM (Context Engineering)
*   **DiaryProvider / DiaryRenderer:** 
    *   При рендере записи вычислять разницу между `$chronicle->current_in_game_date` и `$entry->in_game_date`.
    *   Использовать `Carbon::parse($entry->in_game_date)->diffForHumans($chronicle->current_in_game_date, ['short' => true])`.
    *   Выводить: `[diary — 1 year ago] Текст записи...` (вместо текущего `[diary — earlier]`).

## 2. Frontend (Vue 3)

### 2.1 UI - Часы
*   Создать компонент `InGameClock.vue`.
*   Разместить в шапке сцены (в `LocationBanner.vue` или `SceneToolbar.vue`), справа.
*   Отображает текущее время (например, `31 окт. 1999, 23:00`).
*   Для рассказчика: при клике открывается `input[type="datetime-local"]` и кнопки быстрого шага (`+1м`, `+5м`, `+10м`, `+1ч`, `+1д`).
*   Для игрока: отображается только текст времени (без редактирования).

### 2.2 Composables
*   В `useSceneSession.js` или новый `useInGameTime.js`: добавить метод `updateTime(datetime)`, который вызывает API и обновляет локальное состояние.

## 3. Риски и ограничения
*   **Старые записи:** У существующих записей `in_game_date` будет `null`. В `DiaryRenderer` нужно обрабатывать это (если null — выводить `[diary — earlier]` или использовать `created_at`).
*   **Смена сцены:** При переключении на старую сцену время хроники не должно откатываться назад. Изменения времени в старой сцене меняют только её `started_at_in_game`, но не глобальное время хроники (если сцена не активна).