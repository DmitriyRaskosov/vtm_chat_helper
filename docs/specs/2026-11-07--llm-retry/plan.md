# Plan: Retry при finish_reason=length

## 1. Backend (Laravel)
### 1.1 База данных
*   Миграция: Добавить `is_truncated` (boolean, default false) в таблицу `character_diary_entries`.
*   Модель: Добавить `is_truncated` в `$fillable` и `$casts`.

### 1.2 Copilot Retry (NpcCopilotService)
*   В методе `drafts` после вызова `$this->chat->chatTurn()`:
    *   Проверить `$turn->finishReason === 'length'` или поймать исключение парсера (если JSON сломан).
    *   Если сработало: взять оригинальный массив `$messages`, добавить в конец системное сообщение (или user-сообщение): "Your previous answer was too long. Make drafts shorter and strictly return valid JSON."
    *   Сделать ровно один повторный вызов `chatTurn`.
    *   Если повторно `length` или парсер снова упал — выбросить `LlmResponseInvalidException`.

### 1.3 Дневник Retry (DiaryWriterService и DiarySummarizerService)
*   Создать трейт `RetriesLlmOutput` (в `app/Diary/`):
    *   Метод `callWithRetry(ChatProvider $chat, array $messages, array $options): ChatTurn`.
    *   Делает первый вызов. Если `finishReason === 'length'`:
        *   Берет последние 200 символов из `$turn->content`.
        *   Добавляет в `$messages` сообщение ассистента (сгенерированный текст) и сообщение пользователя: "Продолжи текст с этого места. Выведи только продолжение."
        *   Делает второй вызов.
        *   Склеивает `content` первого и второго ответов.
        *   Если второй тоже `length`, возвращает склеенный текст и флаг `is_truncated = true`.
*   Использовать этот трейт в `DiaryWriterService` и `DiarySummarizerService`.
*   Передавать `is_truncated` в `DiaryEntryRepository` при сохранении.

### 1.4 API
*   `DiaryEntryResource`: Добавить поле `is_truncated`.

## 2. Frontend (Vue 3)
*   `DiaryView.vue`: Добавить бейдж `[ОБРЕЗАНО]` (оранжевый) в шапку записи, если `is_truncated === true`.

## 3. Риски
*   Retry может сработать дважды и съесть больше токенов (но ограничение в 1 раз минимизирует ущерб).
*   Модель может проигнорировать просьбу «Продолжи» и начать заново. В этом случае текст будет испорчен. Нужно проверить на тестах.