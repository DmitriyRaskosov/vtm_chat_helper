# Plan: Боевая Секвенция Сцены (Этап 3: Синхронизация)

## 1. Backend (Laravel)
### 1.1 Лог боя
*   Миграция: добавить `combat_encounter_id` (nullable, FK) в `scene_logs`.
*   `SceneLogService::logDiceRoll`: если есть активная секция, записывать ID.

### 1.2 Автоматизация ходов
*   `CombatService::nextTurn`: 
    *   Обновлять `active_character_id` в `GameSession` или `SceneContext` на текущего участника.
    *   Если ход переходит к игроку — эмитить `CombatTurnChanged` (ShouldBroadcastNow).

### 1.3 WebSockets (События)
*   `CombatTurnChanged` (ShouldBroadcastNow) — в `private-scene.{id}`.
*   `CombatStateUpdated` (при добавлении/удалении участников).

## 2. Frontend (Vue 3)
### 2.1 Слушатели
*   В `useCombatTracker.js` подписаться на `CombatTurnChanged`. Обновлять локальное состояние.
*   Если `active_character_id === myCharacterId`, играть звук (`new Audio()`).

### 2.2 Фильтрация
*   В `useSceneBattleLog.js`: если есть активный бой, показывать только логи с этим `combat_encounter_id`.