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
  - `POST /api/characters` — создание PC/NPC/гуля
- Может отправлять сообщения от имени НПС по `character_id` ([[API/Messages]]).
- Видит и правит любой лист хроники ([[API/Characters]]). Copilot по-прежнему только для NPC, не для гулей.

## Player (игрок)

- Все пользователи после первого — роль `player`.
- Видят сцены активной игровой сессии, их сообщения и свой composer в активной сцене.
- Не видят панель рассказчика и не могут вызывать copilot / RAG search / чужой или NPC `character_id`.
- Могут отправить реплику от своего единственного PC (`character_id` = свой вампир) и от гулей этого PC (`domitor_character_id`).
- Видят и правят лист своего PC и гулей этого PC ([[Features/Characters]]). Чужие листы — 403.
- Не могут управлять жизненным циклом [[Features/Scenes|сцен]].

## Реализация

- Enum `App\Enums\UserRole`
- Middleware `EnsureStoryteller` на маршрутах в `routes/api.php`
- Проверка владельца и типа `character_id` в `StoreMessageRequest::authorize()`; входной `npc_name` запрещён validation rules
