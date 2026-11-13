# Plan: Глобальная библиотека персонажей (Этап 7)

**Status:** Completed
**Date:** 2026-11-13    

## 1. Подход и архитектура

**Схема БД (Зеркальные таблицы).**
Создаем 5 таблиц (с префиксом `library_`):
1. `library_characters` — id, user_id, name, clan_id, sect_id, nature, demeanor, concept, generation, stats (jsonb), sheet_data (jsonb), timestamps.
2. `library_character_biographies` — library_character_id, summary, full_text, principles, motivation, fears, desires, behavioral_rules.
3. `library_character_disciplines` — library_character_id, discipline_id, level.
4. `library_character_powers` — library_character_id, power_id.
5. `library_character_items` — library_character_id, name, quantity, description, is_equipped.

**Сервис `LibraryCharacterService`.**
*   `createFromCharacter(User $user, Character $character)`: 
    Создает `library_character`, копирует JSON. Копирует био. Копирует предметы из `character_items`. Копирует дисциплины из `character_disciplines`.
*   `instantiateInChronicle(LibraryCharacter $libraryChar, Chronicle $chronicle, User $user)`:
    Создает `WorldEntity` и `Character`. Копирует JSON. Копирует био (создает v1 в `character_biography_versions`). Копирует предметы. Копирует дисциплины.
    **Синхронизация дисциплин:** после создания `character_disciplines`, берет их и генерирует из них JSON-массив для поля `stats['disciplines']` (формат `slug => dots`), чтобы сетка чарлиста заработала.
    Возвращает созданного `Character`.

**API и Роутинг.**
*   `GET /api/library-characters` — список шаблонов юзера.
*   `POST /api/library-characters` (body: `character_id`) — сохранить персонажа в библиотеку.
*   `DELETE /api/library-characters/{libraryCharacter}` — удалить шаблон.
*   `POST /api/chronicles/{chronicle}/library-characters/{libraryCharacter}/import` — добавить шаблон в хронику.
Все маршруты требуют авторизации. Policy проверяет, что `libraryCharacter->user_id === auth()->id()`.

**Frontend (ЛК).**
*   В ЛК добавляются вкладки: «Персонажи хроник» и «Библиотека».
*   В карточку персонажа (в списке) добавляется кнопка «В библиотеку».
*   На вкладке «Библиотека» — плитка с шаблонами. При клике на шаблон — выбор хроники (dropdown) и кнопка «Импортировать».

## 2. Затрагиваемые файлы

**Infra / DB:**
- `database/migrations/xxxx_create_library_characters_tables.php`

**Backend:**
- `app/Models/LibraryCharacter.php` (и связанные модели).
- `app/Characters/LibraryCharacterService.php`.
- `app/Http/Controllers/LibraryCharacterController.php`.
- `app/Policies/LibraryCharacterPolicy.php`.
- `routes/api.php`.

**Frontend:**
- `frontend/src/views/ProfileView.vue` — добавление табов.
- `frontend/src/components/profile/LibraryCharacterList.vue` — новый компонент.
- `frontend/src/components/profile/ImportCharacterModal.vue` — модалка выбора хроники.
- `frontend/src/composables/useLibraryCharacters.js`.

## 3. Верификация AC

| AC | Как проверяется |
|---|---|
| AC-1 | Миграции применяются без ошибок. |
| AC-2 | `LibraryCharacterTest::test_save_character_to_library` (проверка, что предметы и дисциплины скопировались). |
| AC-3, AC-4 | Ручная проверка UI (вкладка, кнопка импорта). |
| AC-5 | `LibraryCharacterTest::test_import_syncs_disciplines` (проверка, что после импорта `stats['disciplines']` заполнился). |
| AC-6 | `LibraryCharacterTest::test_library_is_independent` (меняем статы персонажа в хронике — шаблон не меняется). |
| AC-7 | `LibraryCharacterTest::test_user_cannot_access_others_library`. |

## 4. Риски

*   **Риск:** При импорте забыть скопировать `character_powers` (силы дисциплин). 
    **Митигация:** В сервисе `instantiateInChronicle` явно прописать копирование всех дочерних таблиц.
*   **Риск:** Расхождение `stats.disciplines` и реляционных таблиц. 
    **Митигация:** При импорте принудительно перезаписывать `stats.disciplines` на основе реляционных данных.

## 5. Допущения

*   [Assumption] Версии биографии (character_biography_versions) в библиотеке не нужны — храним только актуальную.