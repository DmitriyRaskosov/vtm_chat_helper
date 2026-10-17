# 034a — Фабрики: 7 рабочих, остальные убрать

- **Stage:** 2
- **Part:** 1
- **Status:** done
- **Depends on:** 018p
- **Risk:** med (общий PK `world_entities` ↔ `characters`)
- **Разрешённые зависимости:** нет

## Goal

Семь фабрик (User, Chronicle, GameSession, Scene, SceneParticipant, Character, Message) создают валидные записи, `CharacterFactory` создаёт и строку `world_entities` с тем же id, а у остальных моделей нет ни `HasFactory`, ни stub-фабрики.

## Context

- В 017 созданы 14 stub-фабрик с пустым `definition()` ради снятия записей baseline (`HasFactory<XFactory>` без класса).
- В тестах около 35 вызовов `::create(`; большинство сценариев создаёт данные через API и сервисы. Фабрики нужны прежде всего матрице доступа (030c).
- Персонаж = строка `world_entities` (`entity_type = 'character'`) + строка `characters` с тем же `id`; составной FK `(id, entity_type)`, CHECK `characters_entity_type_check`, `characters_type_check`, `characters_player_user_check` (у игрока `user_id` обязателен, у NPC запрещён). Триггер `prevent_world_entity_delete`.
- Модели с `HasFactory`, которым фабрика не нужна: `CharacterBiography`, `CharacterBiographyVersion`, `CharacterDiscipline`, `CharacterPower`, `Location`, `SceneContext`, `WorldEntity`, `WorldRelation`.
- `.cursor/rules/database.mdc` §Фабрики.

## Scope

- `database/factories/{User,Chronicle,GameSession,Scene,SceneParticipant,Character,Message}Factory.php` — заполнить
- `database/factories/{CharacterBiography,CharacterBiographyVersion,CharacterDiscipline,CharacterPower,Location,SceneContext,WorldEntity,WorldRelation}Factory.php` — удалить (с оговоркой в шаге 1)
- `app/Models/{CharacterBiography,CharacterBiographyVersion,CharacterDiscipline,CharacterPower,Location,SceneContext,WorldEntity,WorldRelation}.php` — только удаление `use HasFactory` и PHPDoc `@use HasFactory<...>`
- `phpstan-baseline.neon` — только если записи исчезают (удалить вручную с комментарием)
- `tests/Feature/FactoriesTest.php` (создать)
- `tests/Feature/*.php` — замена `::factory()` удаляемых фабрик, если такие вызовы есть

**Out of scope:** массовый перевод существующих тестов на фабрики (делается по месту в 030c и далее); `UserFactory` состояния, которые уже есть (не ломать `storyteller()`); сидеры.

## Steps

1. `rg "(CharacterBiography|CharacterBiographyVersion|CharacterDiscipline|CharacterPower|Location|SceneContext|WorldEntity|WorldRelation)::factory" tests database` — если вызов есть, фабрику **не удалять**, заполнить и указать в отчёте.
2. `ChronicleFactory`, `GameSessionFactory` (`active()`, `archived()`), `SceneFactory` (`active()`, `closed()`, привязка к сессии), `MessageFactory` (scene, user, body; `ooc()`), `SceneParticipantFactory` (`npc()`, `player()`, `left()`; курсоры `entered_message_id`/`last_diary_message_id` = null).
3. `CharacterFactory`:
   - `configure()` → `afterMaking` (или `creating`-хук фабрики): создать `WorldEntity` (`chronicle_id`, `entity_type = WorldEntityType::Character`, `canonical_name`, `slug`) и присвоить его `id` персонажу до вставки;
   - состояния `npc()` (`user_id = null`) и `player(?User $user = null)` (создаёт пользователя-игрока, если не передан);
   - `chronicle_id` персонажа и сущности совпадают;
   - если удобнее и надёжнее — делегировать в `WorldEntityService::create(...)` (тот же путь, что в приложении). Выбор объяснить в отчёте.
4. Удалить 8 stub-фабрик и `HasFactory` у их моделей.
5. `FactoriesTest`: каждая из 7 фабрик создаёт запись; `Character::factory()->npc()` и `->player()` создают пару `world_entities`+`characters` с одним id; `Character::factory()->state(['user_id' => null])->player()...` (игрок без пользователя) падает на CHECK — проверить `assertThrows(QueryException::class)`; удаление `world_entities` персонажа падает на триггере.

## Definition of Done

- [ ] 7 фабрик с непустым `definition()`; `FactoriesTest` зелёный
- [ ] `rg "return \[\];" database/factories` → пусто
- [ ] `rg "HasFactory" app/Models` → только User, Chronicle, GameSession, Scene, SceneParticipant, Character, Message
- [ ] baseline: число записей не выросло (до/после в отчёте)
- [ ] `sail artisan test` — зелёные, тестов ≥ 84 + новые; `sail pint --test` — чисто; phpstan — OK

## Report

Формат — `.cursor/rules/task-execution.mdc` §5. Дополнительно: способ создания `world_entities` в `CharacterFactory` и почему; фабрики, оставленные по шагу 1.
