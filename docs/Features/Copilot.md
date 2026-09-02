# Copilot (фича)

ИИ-помощник рассказчика: генерация черновиков реплик НПС и отправка в активную сцену.

**Статус:** MVP готов (этапы 5–6). См. [[Project/Roadmap]].

## Продуктовое поведение

Рассказчик в боковой панели `ChatView.vue`:

1. Вводит **имя НПС** и **ситуационный промпт** (тон, контекст, что должно произойти)
2. Жмёт **Сгенерировать** → API возвращает **3 черновика** и ID сохранённого запроса (не отправляются автоматически)
3. Выбирает черновик, **редактирует** при необходимости
4. Жмёт **Отправить в чат** → сообщение в активной сцене с `author` = имя НПС

Игроки панель не видят. Обычная отправка от своего имени не меняется.
Copilot доступен только для активной сцены. Старые события поступают через иерархические summaries, последние сообщения остаются дословными; подробнее в [[Architecture/Context]].

## Поток данных

```mermaid
sequenceDiagram
    participant ST as Storyteller_UI
    participant API as Laravel_API
    participant RAG as RagSearcher
    participant Ollama as Ollama_qwen3

    ST->>API: POST /copilot/drafts + scene_id
    API->>API: Context Builder в бюджете 12000 токенов
    API->>RAG: search summaries + messages
    API->>Ollama: system + prompt + raw + summaries + RAG; num_ctx=16384
    Ollama-->>API: JSON с 3 репликами
    API->>API: сохранить copilot_requests + context metadata
    API-->>ST: copilot_request_id + drafts[]

    ST->>API: POST /messages + request ID + draft index
    API->>API: проверить и связать запрос, сохранить + RAG index
    API-->>ST: message author=НПС
```

## API

- Генерация: [[API/Copilot]]
- Отправка: [[API/Messages]] с `npc_name`

## UI

См. [[Architecture/Frontend]].

## Трассировка

Успешные генерации сохраняются отдельно от событий мира. Исходный prompt рассказчика и невыбранные drafts не становятся сообщениями чата и не индексируются в RAG. Итоговое отредактированное сообщение связано с одной генерацией и индексом выбранного draft.

## Вне scope (MVP)

- Таблицы `npcs`, `events`, `relationships`, файлы `world/`
- Стриминг, `laravel/ai`
- Файловый лор и карточки НПС в контексте генерации
