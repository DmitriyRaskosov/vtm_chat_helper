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

## Инфраструктура (Docker)
* **laravel.test** — PHP-FPM, HTTP API.
* **reverb** — Laravel Reverb (`php artisan reverb:start`, порт 8080 в сети compose); наружу пробрасывается для браузера (например `8081` на хост).
* **frontend** — Vite; прокси `/api` и `/broadcasting/auth` на Laravel.

## Real-time (WebSockets)
* Broadcaster: `reverb` (`BROADCAST_CONNECTION=reverb`). События — `ShouldBroadcastNow`, обёртка `SafeBroadcaster` (не роняет HTTP при падении Reverb).
* Приватный канал сцены: `private-scene.{scene_id}`; доступ в `routes/channels.php` — активный член хроники сцены.
* События: `MessageSent`, `CharacterStatsUpdated`, `UserTyping`, `DiceRolled`, `SceneUpdated`.
* SPA: Laravel Echo (`frontend/src/echo.js`), авторизация канала через `apiRoot` → `POST /broadcasting/auth`. При обрыве WS >5 с — резервный polling (`useConnectionStatus`), догон по `after_id` / `lastId`.

## Frontend (Vue 3)
* **Composables:** Вся логика вынесена из компонентов (`useChatMessages`, `useSceneSession`, `useDraggable`, `useConnectionStatus`).
* **Layout:** Узкий сайдбар (навигация) -> Центральная сцена (чат IC/OOC, композер) -> Правая панель (участники, Copilot, События).
* **Плавающие окна:** Панели инструментов (Кубы, Чарлист, Заметки, Дневник) рендерятся через `position: fixed` (вне DOM-дерева колонки), чтобы их можно было двигать и выносить за пределы экрана.
* **Стейт:** Глобальное состояние через `auth.js` (реактивные refs). Без Pinia.
