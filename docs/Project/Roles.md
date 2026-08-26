# Роли

## Storyteller (рассказчик)

- Первый зарегистрированный пользователь автоматически получает роль `storyteller`.
- В API: `user.role === "storyteller"`, `user.is_storyteller === true`.
- Доступ к панели copilot в UI ([[Architecture/Frontend]]).
- Эндпоинты только для рассказчика (middleware `storyteller`):
  - `GET /api/rag/search`
  - `POST /api/copilot/drafts`
- Может отправлять сообщения от имени НПС (`npc_name` в [[API/Messages]]).

## Player (игрок)

- Все пользователи после первого — роль `player`.
- Видят только общий чат и свой composer.
- Не видят панель рассказчика и не могут вызывать copilot / RAG search / `npc_name`.

## Реализация

- Enum `App\Enums\UserRole`
- Middleware `EnsureStoryteller` на маршрутах в `routes/api.php`
- Проверка `npc_name` в `StoreMessageRequest::authorize()`
