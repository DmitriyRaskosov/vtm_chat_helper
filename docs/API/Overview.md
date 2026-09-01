# API Overview

Все маршруты в `routes/api.php` с префиксом `/api`.

## Аутентификация

Защищённые маршруты: заголовок `Authorization: Bearer <token>` (Sanctum personal access token).

Публичные: `POST /api/register`, `POST /api/login`.

## Эндпоинты

| Метод | Путь | Auth | Роль | Документация |
|-------|------|------|------|--------------|
| POST | `/api/register` | — | — | [[API/Auth]] |
| POST | `/api/login` | — | — | [[API/Auth]] |
| GET | `/api/user` | sanctum | any | [[API/Auth]] |
| POST | `/api/logout` | sanctum | any | [[API/Auth]] |
| GET | `/api/game-sessions/active` | sanctum | any | [[API/Scenes]] |
| POST | `/api/game-sessions` | sanctum | storyteller | [[API/Scenes]] |
| POST | `/api/game-sessions/{id}/scenes` | sanctum | storyteller | [[API/Scenes]] |
| PATCH | `/api/scenes/{id}/activate` | sanctum | storyteller | [[API/Scenes]] |
| PATCH | `/api/scenes/{id}/close` | sanctum | storyteller | [[API/Scenes]] |
| GET | `/api/messages` | sanctum | any | [[API/Messages]] |
| POST | `/api/messages` | sanctum | any (+ npc: ST) | [[API/Messages]] |
| GET | `/api/rag/search` | sanctum | storyteller | [[API/RAG]] |
| POST | `/api/copilot/drafts` | sanctum | storyteller | [[API/Copilot]] |

## Ошибки

- Валидация: JSON **422** с полями ошибок
- Неавторизован: **401**
- Не рассказчик на ST-only маршрутах: **403**
- Ollama недоступна (copilot): **503**
- Невалидный ответ модели (copilot): **502**
- Недоступная для действия сцена: **409**

Не добавлять редиректы на `/login` и `/chat`.
