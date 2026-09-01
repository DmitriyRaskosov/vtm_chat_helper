# Роли

## Storyteller (рассказчик)

- Первый зарегистрированный пользователь автоматически получает роль `storyteller`.
- В API: `user.role === "storyteller"`, `user.is_storyteller === true`.
- Доступ к панели copilot в UI ([[Architecture/Frontend]]).
- Эндпоинты только для рассказчика (middleware `storyteller`):
  - `GET /api/rag/search`
  - `POST /api/copilot/drafts`
  - создание игровых сессий
  - создание, активация и закрытие сцен
- Может отправлять сообщения от имени НПС (`npc_name` в [[API/Messages]]).

## Player (игрок)

- Все пользователи после первого — роль `player`.
- Видят сцены активной игровой сессии, их сообщения и свой composer в активной сцене.
- Не видят панель рассказчика и не могут вызывать copilot / RAG search / `npc_name`.
- Не могут управлять жизненным циклом [[Features/Scenes|сцен]].

## Реализация

- Enum `App\Enums\UserRole`
- Middleware `EnsureStoryteller` на маршрутах в `routes/api.php`
- Проверка `npc_name` в `StoreMessageRequest::authorize()`
