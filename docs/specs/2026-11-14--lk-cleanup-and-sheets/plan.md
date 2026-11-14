# Plan: Рефакторинг ЛК и Чарлистов

**Status:** Completed
**Date:** 2026-11-14

## 1. Подход и архитектура

**Навигация и вкладки.**
Удаляется роут и компонент `CharacterListView.vue`. Навигация ведет в ЛК и Мастерскую.

**Создание персонажа.**
*   Кнопка «Создать персонажа» в ЛК и Мастерской ведет на форму создания (внутри ЛК/Мастерской).
*   В `StoreCharacterRequest.php` валидация `character_type` делается опциональной. Если создает игрок, бэкенд принудительно ставит `PC`. Если создает рассказчик, `character_type` обязателен.
*   При создании персонаж сохраняется в `LibraryCharacter` (Библиотека).

**Чарлисты (Кнопки).**
*   В карточках (в `ProfileView.vue` и Мастерской) блок кнопок меняется:
    *   Кнопка «Интерактивный лист» -> ведет на `CharacterSheetView.vue`.
    *   Кнопка «Фото листа» -> открывает модалку с картинкой (если `sheet_image_path` не null).

**Уведомления об изменениях (AC-5).**
*   В таблицу `characters` добавляется колонка `updated_by_storyteller_at` (timestamp, nullable).
*   При сохранении персонажа рассказчиком (проверка на `auth()->user()->isStorytellerIn(...)`), эта колонка обновляется.
*   Создается новый эндпоинт: `PATCH /api/characters/{character}/acknowledge-update`, который просто сбрасывает `updated_by_storyteller_at` в `null`.
*   Фронтенд (ЛК игрока): 
    *   Если `updated_by_storyteller_at` не null, в углу карточки отображается красный бейдж `!`. При наведении — всплывает тултип «Рассказчик внёс изменения в чарлист».
    *   При клике на бейдж отправляется PATCH-запрос на `acknowledge-update`, запускается локальный таймер на 5 секунд. Через 5 секунд бейдж скрывается.

**Мастерская (Бейджи хроник).**
*   В `WorkshopView.vue` к карточкам добавляется название хроники (через связь `character->chronicle->title`).

## 2. Затрагиваемые файлы

**Infra / DB:**
- `database/migrations/xxxx_add_updated_by_storyteller_at_to_characters_table.php`
- `app/Models/Character.php` — добавить поле.

**Backend:**
- `app/Http/Requests/StoreCharacterRequest.php` — фикс `character_type`.
- `app/Character/CharacterCreationService.php` — автоустановка типа и `updated_by_storyteller_at`.
- `app/Http/Controllers/CharacterSheetController.php` — сохранение времени storyteller'а и метод `acknowledgeUpdate`.
- `routes/api.php` — удаление старых роутов вкладки, добавление нового эндпоинта.

**Frontend:**
- `frontend/src/views/ProfileView.vue` — разделение кнопок чарлиста, красный бейдж `!` с тултипом и логикой 5-секундного скрытия.
- `frontend/src/views/WorkshopView.vue` — фикс кнопки «Создать», добавление бейджа хроники.
- `frontend/src/router.js` — удаление роута `CharacterListView`.
- `frontend/src/components/layout/AppNav.vue` — удаление ссылки на старую вкладку.
- `frontend/src/components/profile/SheetImageModal.vue` — новая модалка для фото листа.
- `frontend/src/composables/useCharacterList.js` (или аналог) — добавление метода `acknowledgeUpdate`.

## 3. Верификация AC

| AC | Как проверяется |
|---|---|
| AC-1 | Ссылка исчезла из меню. |
| AC-2, AC-3 | `CharacterCreationTest::test_player_auto_pc`, `test_storyteller_can_choose_npc`. |
| AC-4 | Ручная проверка: две кнопки в ЛК и Мастерской. |
| AC-5 | `CharacterApiTest::test_acknowledge_update_clears_flag` + ручная проверка (красный `!`, тултип, исчезновение через 5 сек). |
| AC-6 | Ручная проверка Мастерской (названия хроник). |

## 4. Риски

*   **Риск:** Сломать создание персонажа при удалении `character_type` из обязательных полей для игроков. **Митигация:** Тесты на создание от лица игрока и от лица рассказчика.
*   **Риск:** Удаление `CharacterListView` сломает какие-то глубокие ссылки. **Митигация:** Проверить, что нет других компонентов, ссылающихся на этот роут.
*   **Риск:** Баг с таймером (если компонент размонтируется раньше, чем пройдет 5 секунд). **Митигация:** Очищать `setTimeout` при `onUnmounted`.

## 5. Допущения

*   [Assumption] Флаг `updated_by_storyteller_at` будет сбрасываться только при клике на бейдж. Если игрок просто зайдет на лист персонажа, флаг не сбросится.
*   [Assumption] В Мастерской можно создать персонажа в библиотеку, и оттуда импортировать в нужную хронику.