# 024 — ActiveSceneResolver и проверка персонажа для сцены

- **Stage:** 2
- **Part:** 1
- **Status:** done
- **Depends on:** 023
- **Risk:** med
- **Разрешённые зависимости:** нет

## Goal

Поиск активной сцены текущей хроники выполняется одним классом `App\Scene\ActiveSceneResolver`, а проверка «персонаж из той же хроники и не в архиве» — одним методом модуля Character.

## Context

- Три копии поиска сцены:
  - `ChatController::resolveScene(?int $sceneId, ?int $chronicleId, bool $mustBeActive)` — `index` принимает **неактивную** сцену по id (`mustBeActive = false`), `store` требует активную;
  - `CopilotController::drafts` — требует активную сцену в активной сессии;
  - `CharacterSheetController::visibleCharacterIdsForPlayer` — активная сцена активной сессии (добавлена в 005).
- Коды и тексты: `409 There is no available scene in the active game session.`, `409 Messages can only be posted to the active scene.`, `409 Copilot requires an active scene.`
- Две копии проверки персонажа: `ChatController::resolveWorldAuthor` и `CopilotController::resolveNpcIdentity` — `409 Character does not belong to this chronicle.`, `409 Character is archived.`
- После 019 хроника — из `CurrentChronicle`; после 018 есть `ConflictException`.

## Scope

- `app/Scene/ActiveSceneResolver.php` (создать)
- `app/Scene/Exceptions/*` — исключения сцены (наследники `ConflictException`)
- `app/Character/CharacterSceneGuard.php` (создать; имя можно уточнить) или метод в существующем сервисе Character
- `app/Character/Exceptions/*`
- `app/Http/Controllers/{Chat,Copilot,CharacterSheet}Controller.php` — только замена поиска сцены и проверки персонажа
- `tests/Feature/ActiveSceneResolverTest.php` (создать), существующие тесты Chat/Copilot/Visibility

**Out of scope:** логика `CopilotRequest` (024a), запись сообщений (025b), авторизация `canSpeakAs` (030a), `SceneProvider` и прочий Context.

## Steps

1. Характеризация: тесты на все 5 текстов/кодов выше через HTTP (если ещё нет).
2. `ActiveSceneResolver`: `forWrite(?int $sceneId): Scene` (активная; по id — тоже должна быть активной), `forRead(?int $sceneId): Scene` (по id — любая сцена активной сессии; без id — активная), `activeOrNull(): ?Scene`. Режимы — точно как сейчас; различие `index`/`store`/Copilot — в тестах.
3. Исключения сцены с прежними текстами вместо `abort_if`.
4. Проверка персонажа: `assertUsableIn(Character $c, Scene $s): void` → `MixedChronicleException` (409, текст «Character does not belong to this chronicle.» — сохранить текущий текст ответа, при расхождении с текстом класса — отдельный класс/сообщение) и `CharacterArchivedException` (409).
5. Заменить три копии поиска и две копии проверки.

## Definition of Done

- [ ] `rg "function resolveScene|function resolveNpcIdentity|function resolveWorldAuthor|function visibleCharacterIdsForPlayer" app/Http` → только то, что осталось тонкой обёрткой (лучше пусто)
- [ ] `rg "->active\(\)" app/Http/Controllers` → пусто
- [ ] коды и тексты 5 ответов из Context не изменились
- [ ] `sail artisan test` — зелёные; `sail pint --test` — чисто; phpstan — OK, baseline не вырос

## Report

Формат — `.cursor/rules/task-execution.mdc` §5. Дополнительно: публичный API `ActiveSceneResolver`; где различаются режимы чтения и записи.
