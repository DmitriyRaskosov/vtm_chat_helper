# Tasks: Фикс регрессий политик и матрицы доступа

**Status:** Completed

## Этап 1: Починка регрессий (Политики)
- [x] Проверить `ScenePolicy`, `CharacterPolicy`, `ChronicleLocationPolicy`. 
      Убедиться, что участники хроники получают доступ к картинкам (не 404).
- [x] Проверить `ChronicleInvitationPolicy::accept`. Убрать зависимость от `ChronicleMember`.
- [x] Проверить `UserNotePolicy`. Убедиться, что доступ к заметкам не зависит от хроники.
- [x] Написать/прогнать изолированные тесты (если есть) для этих политик, чтобы убедиться в починке.

## Этап 2: Фикс матрицы (AccessMatrixTest)
- [x] В `AccessMatrixTest` найти строки `user-notes.*` для `player_other_chronicle`. 
      Заменить `EXPECT_404` на `EXPECT_ALLOWED` (200).
- [x] В `AccessMatrixTest` найти строки `workshop.prompts.*` для `player_other_chronicle`. 
      Заменить `EXPECT_404` на `EXPECT_403`.
- [x] Прогнать `php artisan test --filter=AccessMatrixTest`. Убедиться, что тесты не уходят в skip.

## Этап 3: Финализация (DoD)
- [x] Полный прогон тестов (`php artisan test`).
- [x] Pint и PHPStan.
- [x] Обновить `spec.md` и `tasks.md` (Status: Completed).