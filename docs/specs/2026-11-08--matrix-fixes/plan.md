# Plan: Фикс регрессий политик и матрицы доступа

## 1. Backend (Policies и Логика)
*   **Политики картинок (11 регрессий):**
    *   `ScenePolicy`, `CharacterPolicy`, `ChronicleLocationPolicy`: методы, отвечающие за просмотр картинок (`viewImage`, `viewSheetImage` и т.д.), не должны использовать `GuardsChronicleMembership` как единственный критерий.
    *   Если пользователь — участник хроники (проверка через `ChronicleMember`), он должен получать доступ. `GuardsChronicleMembership` должен возвращать `true` (или не вызываться), если запрос идет от участника.
*   **Политика приглашений:**
    *   `ChronicleInvitationPolicy::accept`: метод не должен требовать членства в хронике (пользователь принимает приглашение именно для того, чтобы вступить). Он должен проверять только валидность токена.
*   **User Notes и Workshop:**
    *   `UserNotePolicy`: убедиться, что проверка идет только по `user_id` (владелец), без привязки к `chronicle_id`.

## 2. Тесты (AccessMatrixTest)
*   **user-notes:\***: изменить ожидание для роли `player_other_chronicle` с `EXPECT_404` на `EXPECT_ALLOWED` (200).
*   **workshop.prompts.\***: изменить ожидание для роли `player_other_chronicle` с `EXPECT_404` на `EXPECT_403` (так как это storyteller-only).
*   **Гарантия:** прогнать тест и убедиться, что `markTestSkipped` больше не срабатывает (0 skipped).