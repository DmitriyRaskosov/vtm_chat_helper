# Spec: Фикс регрессий политик и матрицы доступа

**Status:** Completed
**Date:** 2026-11-08

## 1. Контекст и Цель
После унификации кодов ответов (404 для чужих хроник) в матрице доступа появилось 16 скипов. Из них 11 — это реальные регрессии (участники и рассказчики получают 404 там, где должны получать 200), и 5 — устаревшие ожидания для глобальных маршрутов.

## 2. Acceptance Criteria (EARS)
*   **AC-1 (Регрессии):** WHEN участник хроники запрашивает картинку сцены, листа персонажа или локации, THE system SHALL возвращать 200 (а не 404).
*   **AC-2 (Приглашения):** WHEN пользователь принимает приглашение по токену, THE system SHALL обрабатывать запрос (а не падать с 404 из-за отсутствия членства).
*   **AC-3 (Глобальные маршруты):** WHEN игрок другой хроники запрашивает свои заметки (user-notes), THE system SHALL возвращать 200 (заметки не привязаны к хронике).
*   **AC-4 (Матрица):** THE system SHALL обновить ожидания в AccessMatrixTest для глобальных маршрутов (workshop, user-notes) в соответствии с их реальной логикой.
*   **AC-5 (Защита):** Soft-guard (markTestSkipped) в AccessMatrixTest больше не должен срабатывать ни для одного из этих 16 кейсов.

## 3. Out of Scope
*   Рефакторинг других тестов.

## 4. Post-Implementation Notes

Из 16 скипов матрицы **не было регрессий политик** на картинки и приглашения: авторизация проходила, тесты падали из‑за фикстур и раннера. Для `scenes/{scene}/image` не создавалась строка `scene_contexts`, контроллер отдавал 404 «Location image not found»; для sheet/location image файлы не подкладывались в storage. Для `invitations/{token}/accept` в URI подставлялся placeholder `missing-token` (404 «Invitation not found»), хотя отдельной `ChronicleInvitationPolicy` в проекте нет.

Для глобальных маршрутов скорректированы ожидания `player_other_chronicle`: `user-notes` — allowed (200), `workshop.prompts.*` — 403 (storyteller-only), а не 404 по умолчанию.

В `AccessMatrixFixtures` добавлены `Storage::fake`, валидный токен приглашения, `SceneContext::firstOrCreate` и placeholder PNG для image/sheet routes. В `AccessMatrixTest` для file GET используется `get()`, статус читается через `getStatusCode()` (у `BinaryFileResponse` нет метода `status()` при проксировании через `TestResponse::__call`).

Дополнительно исправлено кэширование проверок `chronicle_members`: хелпер `once()` кэширует по месту вызова, без учёта `chronicle_id`; в `GuardsChronicleMembership`, `User::isStorytellerIn()` и `CharacterPolicy` введён ключевой кэш на запрос (`app()->instance` по user + chronicle).