# Spec: Вырезание рудиментов старого листа и перенос зависимостей (SDD №3 в серии, №13 основной общий)

**Status:** Draft
**Date:** 2026-11-07

## 1. Контекст и Цель
После внедрения нового интерактивного чарлиста (SDD №10-12), на странице `CharacterSheetView.vue` остался старый UI: формы Шапки, Биографии, Инвентаря и Черты (Traits). Эти блоки дублируют функционал и тянут данные из старых таблиц/колонок (`character_traits`, `description`, `inventory`).
Цель — полностью удалить старый UI, перенести все данные в единое JSON-поле `sheet_data`, удалить старые колонки/таблицы из БД и переписать все Context Providers (для Copilot), чтобы они читали новые данные.

## 2. Acceptance Criteria (EARS)
*   **AC-1 (Миграция данных):** THE system SHALL перенести данные из таблицы `character_traits` в `sheet_data->traits`, а также поля `description` и `inventory` в `sheet_data` (в виде структурированных массивов/строк).
*   **AC-2 (Удаление БД):** THE system SHALL удалить таблицу `character_traits` и колонки `description`, `inventory` из таблицы `characters`.
*   **AC-3 (Context Providers):** THE system SHALL обновить `NpcIdentityProvider`, `PersonalityProvider` и `SceneProvider`, чтобы они читали черты, описание и инвентарь из нового JSON `sheet_data`.
*   **AC-4 (Frontend):** THE system SHALL удалить старые компоненты из `CharacterSheetView.vue` (`SheetHeaderSection`, `SheetBiographySection`, `SheetTraitsSection`, `SheetPlaceSection` и т.д.).
*   **AC-5 (API):** THE system SHALL удалить `CharacterTraitController` и `CharacterTraitService` (управление чертами теперь внутри `PATCH /stats`).

## 3. Out of Scope
*   Изменение логики вычисления статов (это уже готово).