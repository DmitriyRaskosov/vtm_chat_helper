# Tasks: WebSockets (Laravel Reverb)

**Status:** Completed

## Этап 1: Инфраструктура
- [x] Добавить сервис `reverb` в compose.yaml с healthcheck.
- [x] Настроить `.env.example` (два набора переменных: для бэка и фронта).
- [x] Установить `laravel/reverb`, настроить broadcasting.
- [x] Настроить Echo на фронте с использованием `api` из `auth.js` как authorizer.

## Этап 2: Backend (События и Каналы)
- [x] Настроить авторизацию приватного канала `private-scene.{id}` в channels.php (только участники сцены).
- [x] Создать 5 Events (MessageSent, CharacterStatsUpdated, UserTyping, DiceRolled, SceneUpdated).
- [x] Внедрить вызовы Events в сервисы (MessageService, CharacterStatsController, SceneLogService, SceneContextService).
- [x] **Тесты:** 
- [x] Feature-тест: отправка сообщения триггерит `MessageSent` (Event::fake).
- [x] Feature-тест: обновление статов триггерит `CharacterStatsUpdated`.
- [x] Тест авторизации: пользователь не может подписаться на чужую сцену.

## Этап 3: Frontend (Слушатели и UI)
- [x] Создать `echo.js`.
- [x] Подключить слушатели в `useChatMessages` (MessageSent), `useCharacterStats` (StatsUpdated), `useBattleLog` (DiceRolled), `useSceneSession` (SceneUpdated).
- [x] Сделать UI индикатора «Печатает...» с троттлингом (раз в 2 сек).
- [x] **Тест:** Вызов `UserTyping` показывает индикатор.

## Этап 4: Fallback (Отказоустойчивость)
- [x] Создать `useConnectionStatus.js` (мониторинг состояния).
- [x] Включение/выключение polling при обрыве/восстановлении связи.
- [x] Добавить синхронизацию `after_id` при реконнекте (дедупликация).
- [x] **Тест:** Мокнуть падение WS, проверить, что включился интервал.

## Этап 5: Финализация (DoD)
- [x] Полный прогон тестов, Pint, PHPStan.
- [x] Сборка фронта.
- [x] Обновить docs/ARCHITECTURE.md (добавить сервис Reverb).
- [x] Обновить spec.md и tasks.md (Status: Completed).