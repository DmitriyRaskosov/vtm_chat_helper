# Лор (API)

ST-only статьи канона хроники. Доступ NPC: уровень статьи `0`–`5` входит в набор допуска персонажа, плюс редкие исключения; ситуационные — только grant/deny. UI: [[Features/World]], допуск на [[Features/Characters|листе]]. Канон: [[Architecture/Lore]].

**Auth:** sanctum + `storyteller`. Игрок — **403**.

Хроника: optional `chronicle_id`, иначе служебная первая. Статья другой хроники — **404**.

Сохранение всегда `approved` (как биография с листа). Причина версии служебная («Правка с экрана мира»). После записи пересобирается `lore_chunks`. Повтор без изменений текста, видимости и грифа — **200**, новая версия не создаётся; привязки сущностей и исключения всё равно заменяются, если переданы.

## GET /api/lore

`lore` — не archived; `archived` — скрытые. Элемент списка: `id`, `title`, `kind`, `visibility`, `classification` (`0`–`5`), `situational`, `status`, `current_version` (без текста).

## GET /api/lore/{loreEntry}

Полная статья: список плюс `canonical_text`, `entity_ids`, `entities` (`id`, `canonical_name`, `entity_type`), `granted_character_ids`, `denied_character_ids`.

## POST /api/lore

**201.** Body: `title`, `canonical_text`, optional `kind` (`history`/`place`/`faction`/`person`/`ritual`/`item`/`custom`, дефолт `custom`), `visibility` (`public` / `storyteller_only`, дефолт `public`; на NPC не влияет, в SPA скрыта — всегда шлётся `public`), `classification` (`0`–`5`, дефолт `0`), `situational` (boolean, дефолт `false`), `entity_ids[]`, `granted_character_ids[]`, `denied_character_ids[]`.

`entity_ids` — любые `world_entities` той же хроники (включая персонажей). Исключения — персонажи той же хроники в `character_lore_knowledge`: `grant` открывает статью вне набора уровней (обязателен для `situational`), `deny` скрывает даже при совпадении уровня. Один персонаж не может быть в обоих списках (**422**).

Уровень решает Copilot (членство в наборе, не «потолок»). `visibility` на поиск NPC не влияет.

## PUT /api/lore/{loreEntry}

**200.** Те же поля. Archived править нельзя (**422**). Если `granted_character_ids` или `denied_character_ids` переданы, оба контура исключений заменяются (отсутствующий ключ = пустой список).

## POST /api/lore/{loreEntry}/archive

**200.** `LoreEntryService::archive`, индекс статьи очищается. Не DELETE.

## POST /api/lore/{loreEntry}/restore

**200.** Снова `approved`, индекс пересобирается.

## Ошибки

- 401 без Bearer
- 403 не рассказчик
- 404 другая хроника
- 409 сущность/персонаж другой хроники
- 422 валидация, пустой текст, archived publish, grant и deny на одного персонажа
