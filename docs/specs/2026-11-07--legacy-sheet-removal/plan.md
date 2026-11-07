# Plan: Вырезание рудиментов старого листа

## 1. Backend (Laravel)
### 1.1 Data Migration (Команда)
*   Создать `php artisan characters:migrate-traits-to-sheet`. 
    *   Взять все записи из `character_traits`.
    *   Сформировать из них массив `[{name: key, value: value}]` и записать в `characters.sheet_data['traits']`.
    *   Перенести `description` -> `sheet_data['description']`, `inventory` -> `sheet_data['inventory']`.

### 1.2 Удаление и Context
*   Миграция на удаление `character_traits` и колонок `description`, `inventory`.
*   Обновить `app/Context/Providers/NpcIdentityProvider.php` (читать traits из `$character->sheet_data['traits']`).
*   Обновить `app/Context/Providers/PersonalityProvider.php` (читать traits из JSON).
*   Удалить `CharacterTraitController`, `CharacterTraitService`, `CharacterTraitResource`, `CharacterTrait` модель.

## 2. Frontend (Vue 3)
*   В `CharacterSheetView.vue` удалить все блоки ниже `CharacterSheetGrid`.
*   Удалить старые компоненты `SheetHeaderSection.vue`, `SheetBiographySection.vue`, `SheetTraitsSection.vue`, `SheetPlaceSection.vue`, `SheetMeritsSection.vue` и т.д.
*   Удалить старые composables `useCharacterTraits.js`, `useCharacterBiography.js` и т.д.

## 3. Риски
*   Самое опасное — потеря данных при миграции. Нужно сделать дамп базы перед запуском.
*   Если `NpcIdentityProvider` сломается, Copilot потеряет личность NPC (промпты будут пустыми). Тесты `ContextAssemblerTest` обязательны.
*   **Вынесено из критериев приёмки:** `character_traits` → `sheet_data.traits`; колонки `characters.description` и `characters.inventory` → `sheet_data`, затем эти колонки и таблица удаляются. `NpcIdentityProvider`, `PersonalityProvider`, `SceneProvider` (и дневник) читают черты из JSON. С `CharacterSheetView.vue` убираются старые секции. Отдельного `CharacterTraitController` не было: удаляются `updateTraits` и `PUT /characters/{character}/traits`. Игроку `sheet_data.inventory` в листе не отдаётся.