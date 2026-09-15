# Characters API

Контроллер: `CharacterSheetController`. Канон таблиц: [[Architecture/Database]]. UI: [[Features/Characters]].

Хроника без picker: `chronicle_id` optional; иначе служебная (`Chronicle::resolveId`). Copilot для гулей нет: черновики остаются NPC-only ([[API/Copilot]]).

Права на лист: рассказчик — любой персонаж хроники; игрок — свой PC (`characters.user_id`) и гули этого PC (`domitor_character_id`). Создание — только ST.

## GET /api/character-sheet/catalog

**Auth:** sanctum.

Каталог ключей V20 (`config/character_sheet.php`) и seed дисциплин `ruleset=v20`.

**Response 200:** `catalog` (attributes / abilities / backgrounds / virtues / other / health_boxes), `traits` (плоский список), `disciplines` (`id`, `key`, `display_name`, `ruleset`).

## GET /api/characters

**Auth:** sanctum.

**Query:** `chronicle_id` optional.

ST: все активные вампиры/NPC хроники, гули вложены в `ghouls`. Игрок: свой PC + его гули. Чужих персонажей нет. `archived` — скрытые персонажи хроники (плоский список); у игрока всегда `[]`.

```json
{
  "characters": [
    {
      "id": 1,
      "canonical_name": "Анна",
      "character_type": "player",
      "user_id": 2,
      "ghouls": [{ "id": 3, "canonical_name": "Иван", "character_type": "ghoul" }]
    }
  ],
  "archived": []
}
```

## POST /api/characters

**Auth:** sanctum + `storyteller`. **201.**

| Поле | Правила |
|------|---------|
| `canonical_name` | required string, max 120 |
| `character_type` | `player` / `npc` / `ghoul` |
| `user_id` | required if player; exists users; unique PC |
| `domitor_character_id` | required if ghoul; exists characters той же хроники |
| `chronicle_id` | optional |
| `clan_entity_id`, `sire_character_id` | optional |
| `generation` | optional 4–15 |
| `nature`, `demeanor`, `concept` | optional strings |

Ответ: агрегат листа (`character`). 422 — имя/алиас/тип; 409 — чужая хроника у связей.

## GET /api/characters/{character}

**Auth:** sanctum, доступ к листу.

Агрегат: identity, `experience`, `stats`, `disciplines`, `status` (blood/WP/`health_state`/`revision`, **без** `hunger`), 7 `health_boxes`, `merits_flaws`, `ghouls`, `biography` или `null`, место в мире (`sect_entity_id`/`sect_name`, `clan_entity_id`/`clan_name`, `haven_entity_id`/`haven_name`, `lore_clearance_levels` — отсортированный массив int `0`–`5`). Биография: `summary`, `full_text`, `principles`, `motivation`, `fears`, `desires`, `behavioral_rules`, `current_version`, `status`.

## PATCH /api/characters/{character}

**Auth:** sanctum, доступ к листу.

Identity: `canonical_name`, `nature`, `demeanor`, `concept`, `generation`, `clan_entity_id`, `sire_character_id`. `clan_entity_id` — только активный `entity_type=clan`. Переименование пишет канонический alias. Unique `(chronicle_id, normalized_alias)`.

## PUT /api/characters/{character}/stats

Секционная запись. Body: `stats[]` с `category`, `stat_key`, `display_name`, `value`, optional `maximum`, `sort_order`, `specializations`. Value `0` удаляет строку, кроме **атрибутов** (Appearance 0 у Nosferatu остаётся).

## PATCH /api/characters/{character}/status

Только `blood_pool` (0–50) и/или `temporary_willpower` (0–10) плюс `revision`. Hunger с листа не принимается. Несовпадение revision → **409**.

## PUT /api/characters/{character}/health

`boxes[]`: `index` 0–6, `damage` `bashing`/`lethal`/`aggravated` или null. Канон — клетки; SPA заполняет префикс 0…N одним типом. `health_state` синхронизируется без bump `revision`. Torpor с листа не выставляется.

## PUT /api/characters/{character}/merits

Полная замена. `merits_flaws[]`: `kind` merit/flaw, `name`, `cost` 0–10, optional `note`.

## PATCH /api/characters/{character}/experience

Одно число `experience` ≥ 0 (начисление и трата одним полем).

## PUT /api/characters/{character}/disciplines

`disciplines[]`: `discipline_id`, `level` 0–5. Level 0 снимает дисциплину, если нет изученных powers (иначе 422).

## PUT /api/characters/{character}/biography

**Auth:** sanctum, доступ к листу.

Публикация канона: `summary`, `full_text`, `principles`, `motivation`, `fears`, `desires`, `behavioral_rules` (все optional string). Нужен `summary` или `full_text`. Статус всегда `approved`; причина версии служебная («Правка с листа»). После записи пересобирается индекс `character_bio_chunks`. Повтор без изменений — **200**, новая версия не создаётся. 422 — пустое тело.

## PUT /api/characters/{character}/place

**Auth:** sanctum + `storyteller`. **200.**

Место в мире: `sect_entity_id`, `clan_entity_id`, `haven_entity_id` (все `present`, nullable), optional `lore_clearance_levels` — уникальные int `0`–`5`, сортируются при записи; пустой массив — **422**. Секта — любая активная `faction` (affiliation `member` + `member_of`); предыдущая секта завершается, котерия не трогается. Клан — `characters.clan_entity_id`, только `entity_type=clan`. Гавань — активная location (affiliation `resident` + `located_at`). NPC видит статью, если её `classification` входит в `lore_clearance_levels`, плюс grant, минус deny; `situational` — только grant. Игрок читает имена и допуск в GET листа, писать не может.

## POST /api/characters/{character}/archive

**Auth:** sanctum + `storyteller`. **200.**

`WorldEntityService::archive`: сущность `archived`, `characters.is_active=false`. Вампир/NPC уводит своих активных гулей. Текущее участие в незакрытых сценах снимается. Физического DELETE нет.

## POST /api/characters/{character}/restore

**Auth:** sanctum + `storyteller`. **200.**

Обратная операция: `active`, `is_active=true`. Восстановление вампира/NPC возвращает скрытых гулей этого домитора.

## Ошибки

- 401 без Bearer
- 403 нет доступа к листу / игрок на POST
- 409 revision status или mixed chronicle
- 422 валидация
