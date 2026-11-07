# Tasks: Создание персонажа игроком

**Status:** Completed

## Этап 1: Backend (Права и Валидация)
- [x] Обновить `CharacterPolicy::create`: разрешить игроку создавать персонажа, если тип `player`.
- [x] Обновить `StoreCharacterRequest`: убедиться, что `user_id` и `character_type` не принимаются от игрока напрямую.
- [x] Создать `PlayerCharacterLimitException` (наследник `DomainException`).

## Этап 2: Backend (TDD - Логика и API)
- [x] Написать падающий тест `PlayerCharacterCreationTest`: игрок создает PC, но получает 422 при попытке создать второго.
- [x] Написать падающий тест: игрок не может создать NPC или подделать `user_id`.
- [x] Обновить `CharacterSheetController::store`: принудительно ставить `user_id = Auth::id()` и `character_type = 'player'` для игроков.
- [x] Добавить проверку лимита (1 активный PC) в `CharacterCreationService` или контроллере.
- [x] Добиться прохождения тестов (Green).

## Этап 3: Frontend (Логика и Интерфейс)
- [x] В `useProfile.js` добавить метод `createCharacter(chronicleId, payload)`.
- [x] Создать компонент `CreateCharacterModal.vue`: поля Имя, Клан (из каталога), Концепция. Кнопка «Создать».
- [x] В `ProfileView.vue` добавить кнопку «Создать персонажа» (показывать только если у игрока нет PC).
- [x] Настроить редирект на страницу нового персонажа после создания.

## Этап 4: Финализация (DoD)
- [x] Прогнать все тесты: `docker compose exec laravel.test php artisan test`.
- [x] Прогнать статический анализ: `docker compose exec laravel.test ./vendor/bin/pint` и `phpstan`.
- [x] Сборка фронта: `cd frontend && npm run build`.
- [x] Сверить с Acceptance Criteria из `spec.md`.
- [x] Обновить статус в `spec.md` и `tasks.md` на `Completed`.