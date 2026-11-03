# Plan: Создание персонажа игроком

## 1. Backend (Laravel)

### 1.1 Авторизация и Валидация
*   **CharacterPolicy::create:** 
    *   Обновить метод. Разрешить `$user->isStorytellerIn($chronicle)` (как сейчас).
    *   Добавить условие: разрешить `player` создавать персонажа, ЕСЛИ `$characterType === 'player'` (передается из контроллера).
*   **StoreCharacterRequest:**
    *   Убрать возможность игрока передавать `user_id` и `character_type` напрямую (или игнорировать их в контроллере).
    *   Валидация: `canonical_name` (required, string, max:100), `clan_id` (required, exists:canon_clans,id), `concept` (nullable, string).
*   **Логика ограничения (AC-4):**
    *   В `CharacterCreationService` (или контроллере `CharacterSheetController::store`) добавить проверку: 
        Если `character_type === 'player'` и у `Auth::user()` уже есть активный персонаж (`is_active = true`) в этой хронике, выбросить `PlayerCharacterLimitException` (422).

### 1.2 API
*   Использовать существующий маршрут `POST /api/chronicles/{chronicle}/characters`.
*   В контроллере: если запрос идет от игрока, принудительно ставить `user_id = Auth::id()` и `character_type = 'player'`, игнорируя то, что пришло в запросе.

### 1.3 Тестирование (Backend)
*   `PlayerCharacterCreationTest`:
    *   Игрок может создать PC (успех).
    *   Игрок не может создать NPC (ошибка 403 или игнорирование).
    *   Игрок не может подделать `user_id` другого игрока.
    *   Игрок не может создать второго PC в той же хронике (422).
    *   Рассказчик может создавать как и раньше.

## 2. Frontend (Vue 3)

### 2.1 Composables
*   В `useProfile.js` добавить метод `createCharacter(chronicleId, payload)`.
*   Использовать существующий `useCharacterSheet.js` (или API) для получения списка кланов из `GET /api/character-sheet/catalog`.

### 2.2 Компоненты (UI)
*   **ProfileView.vue:** 
    *   Если у игрока `characters.length === 0`, показывать кнопку «Создать персонажа».
    *   Если персонаж есть, кнопку скрыть (выполнение AC-4).
    *   По клику открывать модалку создания.
*   **CreateCharacterModal.vue (создать):**
    *   Поля: Имя (input), Клан (select с опциями из каталога), Концепция (input).
    *   Кнопка «Создать».
    *   При успехе: закрыть модалку, обновить список в ЛК и сделать `router.push` на `/c/{chronicleId}/characters/{newId}`.

## 3. Риски и ограничения
*   **Создание WorldEntity:** Создание персонажа — это сложный процесс (создается запись в `world_entities` и в `characters`). Нужно убедиться, что `WorldEntityService` корректно работает при вызове из `CharacterCreationService` игроком.
*   **Контекст хроники:** Игрок должен создавать персонажа строго в той хронике, в которой он сейчас находится (через `CurrentChronicle`).