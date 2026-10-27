# Архитектура

## Слои (Backend)
- **HTTP:** Тонкие контроллеры, FormRequest (валидация), JsonResource (сериализация).
- **Policies:** Единая точка авторизации (роли, доступ к хроникам).
- **Services (Domain):** Бизнес-логика (Chronicle, Character, Scene, Dice, EventGenerator).
- **Context:** Сборка промптов для LLM (модульная, через интерфейсы провайдеров).
- **Models:** Eloquent (связи, касты).

## Направление зависимостей
`Http -> Services -> Models`
`Context -> Services / Models` (Context читает данные, но не пишет).
`Diary -> Llm / Rag` (генерация текста и векторов).

## Модули (Backend)
### Context (Сборка промптов)
* `ContextAssembler` — собирает секции (сцена, личность, память, отношения) в рамках лимита токенов.
* `Providers` — каждый провайдер отдает свою секцию (например, `SceneProvider` отдает описание, `DiaryProvider` — память).

### Llm & Rag
* `ChatProvider` (DeepSeek) — генерация текста (драфты, дневники, события).
* `EmbeddingProvider` (Ollama в Docker) — векторы для дневников.

### Dice & Scene
* `DiceService` — чистая математика (V20).
* `EventGeneratorService` — собирает контекст (локация, броски) и просит LLM описать исход.

## Frontend (Vue 3)
* **Composables:** Вся логика вынесена из компонентов (`useChatMessages`, `useSceneSession`, `useDraggable`).
* **Layout:** Узкий сайдбар (навигация) -> Центральная сцена (чат IC/OOC, композер) -> Правая панель (участники, Copilot, События).
* **Плавающие окна:** Панели инструментов (Кубы, Чарлист, Заметки, Дневник) рендерятся через `position: fixed` (вне DOM-дерева колонки), чтобы их можно было двигать и выносить за пределы экрана.
* **Стейт:** Глобальное состояние через `auth.js` (реактивные refs). Без Pinia.
