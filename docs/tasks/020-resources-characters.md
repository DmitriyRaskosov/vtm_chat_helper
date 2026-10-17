# 020 — Resources для персонажей

- **Stage:** 2
- **Part:** 3
- **Status:** todo
- **Depends on:** 029
- **Risk:** med (форма JSON листа читается фронтом)
- **Разрешённые зависимости:** нет

## Goal

Ответы маршрутов персонажей сериализуются через `JsonResource`, приватные `listItem`/`archivedList` в `CharacterSheetController` удалены, форма JSON не изменилась.

## Context

- `.cursor/rules/http.mdc` §Resources: сначала тест на форму JSON, потом Resource.
- Сейчас: `index` → `{characters: [listItem], archived: [...]}`, `listItem` и `archivedList` дублируют одинаковый набор полей (`id`, `canonical_name`, `character_type`, `user_id`); `show/update/updateDisciplines/updateBiography/updateTraits/updatePlace/archive/restore/store` → `{character: CharacterSheetReader::aggregate(...)}`; `catalog` → `{disciplines, clans, bloodlines, sects}`.
- После 023 имя — через связь `entity` (eager load); после 026 каталог — из `CharacterCatalog`.
- Единственный существующий Resource — `UserResource` (образец стиля).
- В 025b создан `app/Http/Resources/MessageResource` как заготовка (для списка сообщений). Использовать его как образец стиля Resource (зритель передаётся явно, `auth()` внутри Resource запрещён). В 020 — `CharacterResource` по образцу `MessageResource`.

## Scope

- `app/Http/Resources/CharacterListItemResource.php`, `CharacterSheetResource.php`, `CharacterCatalogResource.php` (создать; имена можно уточнить)
- `app/Http/Controllers/CharacterSheetController.php` — только сериализация
- `app/Character/CharacterSheetReader.php` — только если `aggregate` нужно вернуть DTO для Resource (форма не меняется)
- `tests/Feature/CharacterJsonShapeTest.php` (создать)
- `CharacterSheetController::listItem` / `archivedList` → `CharacterResource`.
- `CharacterSheetController::visibleCharacterIdsForPlayer` — оставить до 030a (Policy).

**Out of scope:** изменение полей ответа; фронт; авторизация; `wrap` по умолчанию у Resource — отключить или использовать так, чтобы ключи `character`/`characters` остались прежними.

## Steps

1. Тест на форму JSON (`assertExactJson` или `assertJsonStructure` + проверка отсутствия лишних ключей) для `GET /characters` (рассказчик и игрок), `GET /characters/{id}`, `POST /characters`, `GET /character-sheet/catalog`. Зелёный до правок.
2. Resources с теми же ключами и типами (int/string/null — как сейчас).
3. Заменить сериализацию в контроллере, удалить `listItem`, `archivedList`.
4. Повторить тест шага 1 без правок.

## Definition of Done

- [ ] тест шага 1 зелёный до и после без изменений
- [ ] `rg "function (listItem|archivedList|serialize)" app/Http/Controllers/CharacterSheetController.php` → пусто
- [ ] запросов SQL на `GET /characters` не больше, чем после 023 (тест `CharacterNameQueryCountTest`)
- [ ] ручная проверка: список персонажей и лист открываются
- [ ] `sail artisan test` — зелёные; `sail pint --test` — чисто; phpstan — OK, baseline не вырос

## Report

Формат — `.cursor/rules/task-execution.mdc` §5.
