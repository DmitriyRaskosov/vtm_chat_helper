# Plan: WebSockets (Laravel Reverb)

## 1. Инфраструктура (Docker)
*   **Docker:** Добавить сервис `reverb` в `compose.yaml` (на базе образа laravel.test, команда `php artisan reverb:start --host=0.0.0.0 --port=8080`).
    *   Настроить healthcheck для Reverb (проверка открытого порта).
*   **Окружение:** 
    *   Для бэкенда: `REVERB_HOST=reverb`, `REVERB_PORT=8080`.
    *   Для фронтенда: `VITE_REVERB_HOST=localhost`, `VITE_REVERB_PORT=8081` (порт, проброшенный из Docker).
*   **Frontend Config:** Настроить `authorizer` в Laravel Echo, чтобы он использовал axios-инстанс `api` из `auth.js` (для передачи Sanctum-токена).

## 2. Backend (Laravel)
*   Установить `laravel/reverb`. Настроить `config/broadcasting.php`.
*   **Каналы (channels.php):**
    *   `private-scene.{scene_id}`: разрешать только участникам сцены (проверка через ScenePolicy).
*   **События:**
    *   `MessageSent` (ShouldBroadcastNow)
    *   `CharacterStatsUpdated` (ShouldBroadcastNow) — отправляется в `private-scene.{scene_id}`.
    *   `UserTyping` (ShouldBroadcastNow)
    *   `DiceRolled` (ShouldBroadcastNow)
    *   `SceneUpdated` (ShouldBroadcastNow)

## 3. Frontend (Vue 3)
*   **Fallback Logic (`useConnectionStatus.js`):** 
    *   Слушаем состояние Echo. При `failed/unavailable` > 5 сек — включаем `setInterval` (polling). При `connected` — выключаем.
    *   *Дедупликация:* При восстановлении WS запрашиваем `load(lastId)`, чтобы догнать пропущенные сообщения. Новые сообщения из WS добавляем только если `id > lastId`.
*   **Интеграция:**
    *   `useChatMessages`: слушаем `MessageSent` (добавляем в чат), `UserTyping` (индикатор).
    *   `useCharacterStats`: слушаем `CharacterStatsUpdated` (проверяем, что `character_id` совпадает, обновляем лист).
    *   `useBattleLog`: слушаем `DiceRolled`.
    *   `useSceneSession`: слушаем `SceneUpdated`.

## 4. Риски и ограничения
*   **Двойная доставка:** Самое опасное при переключении WS↔Polling. Решается проверкой `id > lastId` на клиенте.
*   **WSS в продакшене:** В локальной разработке используем `ws://`, в продакшене потребуется reverse-proxy с TLS для `wss://`. (Сейчас Out of Scope).
*   **ShouldBroadcastNow:** Используем синхронную отправку, так как задержки воркера (даже 100мс) критичны для чата. Но при падении Reverb HTTP-запрос пользователя может замедлиться. Нужно обернуть broadcast в try-catch, чтобы не ронять основной контроллер.
*   **Вынесено из критериев приёмки:** Laravel Reverb; канал `private-scene.{scene_id}` только для участников (`ScenePolicy`). События `ShouldBroadcastNow`: `MessageSent`, `CharacterStatsUpdated`, `UserTyping`, `DiceRolled`, `SceneUpdated`. Статы фильтруются на клиенте по `character_id`. Догрузка после обрыва — `after_id` / `lastId`, дубли с `id <= lastId` отбрасываются. Индикатор набора гаснет через 3 секунды, сигнал не чаще раза в 2 секунды. Порог перехода на polling — 5 секунд.