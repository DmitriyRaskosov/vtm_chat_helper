# Tasks: WebSockets (Laravel Reverb)

**Status:** Draft

## Этап 1: Инфраструктура
- [ ] Добавить сервис `reverb` в compose.yaml с healthcheck.
- [ ] Настроить `.env.example` (два набора переменных: для бэка и фронта).
- [ ] Установить `laravel/reverb`, настроить broadcasting.
- [ ] Настроить Echo на фронте с использованием `api` из `auth.js` как authorizer.

## Этап 2: Backend (События и Каналы)
- [ ] Настроить авторизацию приватного канала `private-scene.{id}` в channels.php (только участники сцены).
- [ ] Создать 5 Events (MessageSent, CharacterStatsUpdated, UserTyping, DiceRolled, SceneUpdated).
- [ ] Внедрить вызовы Events в сервисы (MessageService, CharacterStatsController, SceneLogService, SceneContextService).
- [ ] **Тесты:** 
    - [ ] Feature-тест: отправка сообщения триггерит `MessageSent` (Event::fake).
    - [ ] Feature-тест: обновление статов триггерит `CharacterStatsUpdated`.
    - [ ] Тест авторизации: пользователь не может подписаться на чужую сцену.

## Этап 3: Frontend (Слушатели и UI)
- [ ] Создать `echo.js`.
- [ ] Подключить слушатели в `useChatMessages` (MessageSent), `useCharacterStats` (StatsUpdated), `useBattleLog` (DiceRolled), `useSceneSession` (SceneUpdated).
- [ ] Сделать UI индикатора «Печатает...» с троттлингом (раз в 2 сек).
- [ ] **Тест:** Вызов `UserTyping` показывает индикатор.

## Этап 4: Fallback (Отказоустойчивость)
- [ ] Создать `useConnectionStatus.js` (мониторинг состояния).
- [ ] Включение/выключение polling при обрыве/восстановлении связи.
- [ ] Добавить синхронизацию `after_id` при реконнекте (дедупликация).
- [ ] **Тест:** Мокнуть падение WS, проверить, что включился интервал.

## Этап 5: Финализация (DoD)
- [ ] Полный прогон тестов, Pint, PHPStan.
- [ ] Сборка фронта.
- [ ] Обновить docs/ARCHITECTURE.md (добавить сервис Reverb).
- [ ] Обновить spec.md и tasks.md (Status: Completed).