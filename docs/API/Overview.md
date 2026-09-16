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
| GET | `/api/scenes/{id}/context` | sanctum | storyteller | [[API/Scenes]] |
| PUT | `/api/scenes/{id}/context` | sanctum | storyteller | [[API/Scenes]] |
| GET | `/api/scenes/{id}/participants` | sanctum | storyteller | [[API/Scenes]] |
| POST | `/api/scenes/{id}/participants` | sanctum | storyteller | [[API/Scenes]] |
| PATCH | `/api/scenes/{id}/participants/{character}` | sanctum | storyteller | [[API/Scenes]] |
| GET | `/api/messages` | sanctum | any | [[API/Messages]] |
| POST | `/api/messages` | sanctum | any (+ npc: ST) | [[API/Messages]] |
| GET | `/api/character-sheet/catalog` | sanctum | any | [[API/Characters]] |
| GET | `/api/characters` | sanctum | any | [[API/Characters]] |
| POST | `/api/characters` | sanctum | storyteller | [[API/Characters]] |
| GET | `/api/characters/{character}` | sanctum | own sheet / ST | [[API/Characters]] |
| PATCH | `/api/characters/{character}` | sanctum | own sheet / ST | [[API/Characters]] |
| PUT | `/api/characters/{character}/stats` | sanctum | own sheet / ST | [[API/Characters]] |
| PATCH | `/api/characters/{character}/status` | sanctum | own sheet / ST | [[API/Characters]] |
| PUT | `/api/characters/{character}/health` | sanctum | own sheet / ST | [[API/Characters]] |
| PUT | `/api/characters/{character}/merits` | sanctum | own sheet / ST | [[API/Characters]] |
| PATCH | `/api/characters/{character}/experience` | sanctum | own sheet / ST | [[API/Characters]] |
| PUT | `/api/characters/{character}/disciplines` | sanctum | own sheet / ST | [[API/Characters]] |
| PUT | `/api/characters/{character}/biography` | sanctum | own sheet / ST | [[API/Characters]] |
| PUT | `/api/characters/{character}/place` | sanctum | storyteller | [[API/Characters]] |
| POST | `/api/characters/{character}/archive` | sanctum | storyteller | [[API/Characters]] |
| POST | `/api/characters/{character}/restore` | sanctum | storyteller | [[API/Characters]] |
| GET | `/api/world/entities` | sanctum | storyteller | [[API/World]] |
| POST | `/api/world/entities` | sanctum | storyteller | [[API/World]] |
| POST | `/api/world/entities/{entity}/archive` | sanctum | storyteller | [[API/World]] |
| POST | `/api/world/entities/{entity}/restore` | sanctum | storyteller | [[API/World]] |
| GET | `/api/world/faction-relations` | sanctum | storyteller | [[API/World]] |
| POST | `/api/world/faction-relations` | sanctum | storyteller | [[API/World]] |
| POST | `/api/world/faction-relations/{relation}/end` | sanctum | storyteller | [[API/World]] |
| GET | `/api/world/relations` | sanctum | storyteller | [[API/World]] |
| POST | `/api/world/relations` | sanctum | storyteller | [[API/World]] |
| POST | `/api/world/relations/{relation}/end` | sanctum | storyteller | [[API/World]] |
| POST | `/api/world/events` | sanctum | storyteller | [[API/World]] |
| POST | `/api/world/events/{event}/participants` | sanctum | storyteller | [[API/World]] |
| POST | `/api/world/events/{event}/sources` | sanctum | storyteller | [[API/World]] |
| GET | `/api/lore` | sanctum | storyteller | [[API/Lore]] |
| POST | `/api/lore` | sanctum | storyteller | [[API/Lore]] |
| GET | `/api/lore/{loreEntry}` | sanctum | storyteller | [[API/Lore]] |
| PUT | `/api/lore/{loreEntry}` | sanctum | storyteller | [[API/Lore]] |
| POST | `/api/lore/{loreEntry}/archive` | sanctum | storyteller | [[API/Lore]] |
| POST | `/api/lore/{loreEntry}/restore` | sanctum | storyteller | [[API/Lore]] |
| GET | `/api/rag/search` | sanctum | storyteller | [[API/RAG]] |
| POST | `/api/copilot/drafts` | sanctum | storyteller | [[API/Copilot]] |
| GET | `/api/extract/status` | sanctum | storyteller | [[API/Extract]] |
| POST | `/api/extract` | sanctum | storyteller | [[API/Extract]] |
| GET | `/api/extract/{run}` | sanctum | storyteller | [[API/Extract]] |
| POST | `/api/extract/{run}/candidates/{index}/accept` | sanctum | storyteller | [[API/Extract]] |
| POST | `/api/extract/{run}/candidates/{index}/discard` | sanctum | storyteller | [[API/Extract]] |

## Ошибки

- Валидация: JSON **422** с полями ошибок
- Неавторизован: **401** (в том числе для `api/*` без Bearer; редиректа на `login` нет)
- Не рассказчик на ST-only маршрутах: **403**
- Ollama недоступна (copilot / extract): **503**
- Невалидный ответ модели (copilot / extract): **502**
- Экстрактор отключён (`EXTRACTOR_DRIVER=none`): **503**
- Недоступная для действия сцена: **409**

Не добавлять редиректы на `/login` и `/chat`.
