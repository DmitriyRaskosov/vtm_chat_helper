# Copilot (фича)

ИИ-помощник рассказчика: генерация черновиков реплик НПС и отправка в активную сцену.

**Статус:** готов; `character_id` cutover и финальная архитектура завершены этапом 35. Сжатие истории снято. См. [[Project/Roadmap]].

## Продуктовое поведение

Рассказчик в боковой панели `ChatView.vue`:

1. Добавляет персонажа на сцену блоком «На сцене», затем выбирает текущего **НПС-участника** и вводит ситуационный промпт
2. Жмёт **Сгенерировать** → API возвращает **3 черновика** и ID сохранённого запроса (не отправляются автоматически)
3. Выбирает черновик, **редактирует** при необходимости
4. Жмёт **Отправить в чат** → сообщение в активной сцене с `author` = имя НПС

Игроки панель не видят. Обычная отправка от своего имени не меняется.
Copilot доступен только для активной сцены. Сначала модель выписывает поисковые топики по промпту ST и короткому хвосту сцены **этого** NPC; затем assembler ищет лор/память/правила по топикам и допуску; затем три черновика реплики. Более старые реплики сессии — только если модель вызовет tools на втором вызове. GraphRAG в tools не входит. Подробнее в [[Architecture/Context]].

## Поток данных

```mermaid
sequenceDiagram
    participant ST as Storyteller_UI
    participant API as Laravel_API
    participant Ollama as Ollama_qwen3

    ST->>API: POST /copilot/drafts + scene_id
    API->>API: Context Assembler топиков в бюджете 8000
    API->>Ollama: промпт ST + хвост сцены, без лора и графа
    Ollama-->>API: JSON topics
    API->>API: GraphRAG и правила по топикам и допуску
    API->>API: Context Assembler реплики в бюджете 12000
    API->>Ollama: слои канона; tools optional
    Ollama-->>API: tool calls или JSON с 3 репликами
    API->>API: scoped RetrievalOrchestrator при tool calls
    API->>API: сохранить copilot_requests
    API-->>ST: copilot_request_id + drafts[]

    ST->>API: POST /messages + request ID + draft index
    API->>API: проверить и связать запрос, сохранить + RAG index
    API-->>ST: message author=НПС
```

## API

- Генерация: [[API/Copilot]]
- Отправка: [[API/Messages]] с `character_id`

## UI

См. [[Architecture/Frontend]].

## Трассировка

Успешные генерации сохраняются отдельно от событий мира. Исходный prompt рассказчика и невыбранные drafts не становятся сообщениями чата и не индексируются в RAG. Итоговое отредактированное сообщение связано с одной генерацией и индексом выбранного draft.

Добор истории — [[Architecture/Retrieval]].

## Вне scope (MVP)

- Таблицы `npcs`/`character_users` как отдельный слой, файлы `world/`, storyteller CRUD UI
- Стриминг, `laravel/ai`
