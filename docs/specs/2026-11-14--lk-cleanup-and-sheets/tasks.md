# Tasks: Рефакторинг ЛК и Чарлистов

**Status:** Completed
**Date:** 2026-11-14

## Stage 1: Backend (TDD)
- [x] Миграция `add_updated_by_storyteller_at_to_characters_table`.
- [x] Написать падающий `test_player_auto_pc` и `test_storyteller_can_choose_npc`.
- [x] Исправить `StoreCharacterRequest` (тип опционален для игрока, обязателен для мастера).
- [x] Обновить `CharacterCreationService` (автоустановка PC для игрока).
- [x] Написать падающий `test_storyteller_edit_sets_flag`.
- [x] При редактировании чарлиста рассказчиком обновлять `updated_by_storyteller_at`.
- [x] Написать падающий `test_acknowledge_update_clears_flag`.
- [x] Добавить метод `acknowledgeUpdate` в `CharacterSheetController` и роут `PATCH /characters/{character}/acknowledge-update`.

## Stage 2: Frontend — Удаление и Навигация
- [x] Удалить роут и вьюху `CharacterListView` из `router.js`.
- [x] Убрать ссылку на вкладку «Персонажи» из `AppNav.vue`.
- [x] Убедиться, что кнопка «Создать персонажа» в ЛК и Мастерской вызывает общую форму создания.

## Stage 3: Frontend — Чарлисты и Мастерская
- [x] В `ProfileView.vue` разделить кнопку «Чарлист» на «Интерактивный лист» (переход на `CharacterSheetView`) и «Фото листа» (открывает новую модалку).
- [x] Создать `SheetImageModal.vue` для просмотра картинки (и скрыть/задизейблить кнопку, если картинки нет).
- [x] В `WorkshopView.vue` добавить бейджи хроник к персонажам.
- [x] В `ProfileView.vue` добавить красный бейдж `!`:
    *   Отображается, если `updated_by_storyteller_at` не null.
    *   При наведении показывает тултип.
    *   При клике отправляет запрос на `acknowledge-update` и запускает `setTimeout` на 5 секунд для скрытия бейджа (с очисткой таймера при размонтировании).

## Stage 4: Finalization
- [ ] Ручная проверка: игрок создает PC, мастер создает NPC. Мастер меняет статы игрока — у игрока появляется красный `!`, тултип, клик скрывает его через 5 сек.
- [x] `php artisan test`, `pint`, `phpstan`, `npm run build`.
- [x] Обновить `spec.md` и `tasks.md` (Status: Completed).

## Hotfix
- [x] Убран бейдж «игрок» / «НПС» с карточек вкладки «Библиотека».
- [x] Правка рассказчика шлёт `CharacterEditedByStorytellerEvent` в `private-chronicle.{id}`; кабинет ставит `!` сразу, если `user_id` — текущий игрок.
- [x] Клик по `!` сразу скрывает значок, блокирует повтор и шлёт `acknowledge-update`. Таймер 5 секунд убран; при ошибке запроса значок возвращается.